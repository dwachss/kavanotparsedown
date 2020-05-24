<?
require_once ('Parsedown.php');
require_once ('ParsedownExtra.php');

class KavanotParsedown extends ParsedownExtra {

	function __construct(){
		$this->InlineTypes['/'] []= 'Italic';
		$this->InlineTypes['{'] []= 'Attributes';
		$this->InlineTypes['_'] = ['Cite']; // redefinition
		$this->inlineMarkerList = implode ('', array_keys($this->InlineTypes));
		$this->BlockTypes['-'][] = 'Source';
	}
	
	protected $smartQuotes = array(
		'"..."' => 'adjustEllipsis',
		'"--"' => 'adjustEmDash',
		"'\"'" => 'adjustDoubleQuotes',
		'"\'"' => 'adjustSingleQuotes'
	);
		
	function text($text){
		$text = parent::text($text);
		
		// manipulate the elements by turning them into a real DOMDocument
		// https://stackoverflow.com/a/51666821 to ignore errors
		// https://stackoverflow.com/a/16931835 to use UTF-8
		// https://stackoverflow.com/a/22490902 to not add DTD
		// https://stackoverflow.com/a/29499398 to wrap it in an <div> element (since it needs a single root)
		// we could strip that off, but I'm not concerned about the nonsemantic html
		$dom = DOMDocument::loadHTML('<?xml encoding="UTF-8"><div>'.$text.'</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_HTML_NODEFDTD);
		$xpath = new DOMXpath($dom);
		
		// Source citations use <footer class=source> for the citations. Adjust those to match whatwg recommendations
		foreach ($xpath->query("//footer[@class='source']") as $footer) self::adjustFooter($footer);
		
		// turn <attr> elements into attributes of the following element
		foreach ($xpath->query("//attr") as $attr) self::moveAttributes ($attr);
				
		// smart quotes etc.
		foreach ($this->smartQuotes as $plain => $fancy){
			foreach ($xpath->query("//text()[contains(.,$plain)]") as $node){ // select only text nodes with the target string
				// note that this will fail if the quotes span multiple text nodes. Need to manually edit for that.
				$this->$fancy ($node);
			}
		}

		return $dom->saveHTML($dom->documentElement);
	} // text
	
	static protected function copyAttributes ($from, $to){
		foreach ($from->attributes as $attr) $to->setAttribute($attr->nodeName, $attr->nodeValue);
	}
	
	static protected function adjustFooter ($footer){
		// In my original Kavanot, sources are in the form
		// <blockquote lang=he>
		//    <p>Text</p>
		// 		<footer class=source>Source</footer>
		// </blockquote>
		// According to https://html.spec.whatwg.org/multipage/grouping-content.html#the-blockquote-element:the-blockquote-element-4
		// Quotes and sources should be:
		// <figure>
		//   <blockquote>
		//     <p>Text</p>
		//   </blockquote>
		//   <figcaption>Source</figcaption>
		// </figure>
		// But I've been using a <footer> element *within* the blockquote for the source.
		for ($blockquote = $footer->parentNode; $blockquote && $blockquote->nodeName !== 'blockquote'; $blockquote = $blockquote->parentNode);
		if (is_null($blockquote)) $blockquote = $footer->parentNode; // not actually in a <blockquote> but inside something
		$figure = $blockquote->parentNode;
		if ($figure->nodeName != 'figure'){
			$figure = $blockquote->ownerDocument->createElement ('figure');
			$blockquote->parentNode->insertBefore ($figure, $blockquote);
			$figure->appendChild ($blockquote);
			self::copyAttributes ($blockquote, $figure); // make sure we get the lang attributes moved up
		}
		$figcaption = $blockquote->ownerDocument->createElement ('figcaption');
		while ($footer->firstChild) $figcaption->appendChild ($footer->firstChild);
		self::copyAttributes ($footer, $figcaption);
		$figure->appendChild ($figcaption);
		$footer->parentNode->removeChild ($footer);
	}

	static protected function parseAttributes ($attrString){
		// shortcuts:
		// #string is translated to id="string"
		// .string is translated to class="string"
		// [a-zA-Z]{2} is translated to lang=[a-zA-Z]{2} (since I use the lang= attribute so much)
		$attrString = " $attrString "; // to simplify the regex, search for space-delimited rather than start/end
		// to make this work, we need to actually parse the string; simply splitting on spaces won't account for strings
		// so we pull out quotes. Fortunately HTML doesn't escape quotes; you need to use &quot;
		$strings = array();
		$remover = function ($matches) use (&$strings) {
			$strings []= $matches[0];
			return "\x1a".count($strings)."\x1a";
		};
		$replacer = function ($matches) use (&$strings) {
			return $strings[$matches[1]-1];
		};
		$attrString = preg_replace_callback ('/"[^"]*"/', $remover, $attrString);
		$attrString = preg_replace_callback ("/'[^']*'/", $remover, $attrString);
		$attrString = preg_replace ('/ #(\w+) /', ' id=$1 ', $attrString);
		$attrString = preg_replace ('/ \.(\w+) /', ' class=$1 ', $attrString);
		$attrString = preg_replace ('/ ([a-zA-Z]{2}) /', ' lang=$1 ', $attrString);
		$attrString = preg_replace_callback ('/\x1a(\d)+\x1a/', $replacer, $attrString);
		// trick from https://stackoverflow.com/a/1083843, though SimpleXMLElement is much more strict so I had to use DOMDocument
		$dom = DOMDocument::loadHTML("<element $attrString />",
			LIBXML_HTML_NOIMPLIED | LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_HTML_NODEFDTD);
		$ret = [];
		foreach ($dom->firstChild->attributes as $attr){
			$ret [$attr->nodeName] = $attr->nodeValue;
		}
		return $ret;
	} // parseAttributes
	
	static protected function moveAttributes ($attrNode){
		// apply the attributes of $attrNode to the next node, then delete it.
		// Special case: an attribute on a line by itself will be enclosed in a <p>. Delete that <p>
		if ($attrNode->parentNode->childNodes->length == 1 && $attrNode->parentNode->nodeName == 'p'){
			self::copyAttributes ($attrNode->parentNode, $attrNode);
			$attrNode->parentNode->parentNode->replaceChild ($attrNode, $attrNode->parentNode);
		}
		// skip text nodes etc.
		for ($target = $attrNode->nextSibling; $target && $target->nodeType != XML_ELEMENT_NODE; $target = $target->nextSibling);
		if ($target) self::copyAttributes ($attrNode, $target);
		$attrNode->parentNode->removeChild($attrNode);
	} // moveAttributes
	
	protected function adjustEllipsis ($node){
		$node->textContent = preg_replace ('/\.\.\./', '…', $node->textContent);
	}

	protected function adjustEmDash ($node){
		$node->textContent = preg_replace ('/--/', '—', $node->textContent);
	}
	
	protected function adjustDoubleQuotes ($node){
		$gershayim = '״'; // these are very hard to see in my editor, so I separated these out
		$hebrew = '[א-ת]';
		$node->textContent = preg_replace ("/($hebrew)\"($hebrew)/", "$1$gershayim$2", $node->textContent);
		$node->textContent = preg_replace ("/($hebrew)(\W*)\"(.+?)\"/", '$1$2”$3“', $node->textContent);  // if preceded by Hebrew, assume it's right to left
		$node->textContent = preg_replace ('/"(.+?)"/', '“$1”', $node->textContent);
	}

	protected function adjustSingleQuotes ($node){
		$geresh = '׳'; // these are very hard to see in my editor, so I separated these out
		$hebrew = '[א-ת]';
		$node->textContent = preg_replace ("/($hebrew)'(\W)/", "$1$geresh$2", $node->textContent);
		$node->textContent = preg_replace ("/($hebrew)(\W*)'(.+?)'/", '$1$2’$3‘', $node->textContent);  // if preceded by Hebrew, assume it's right to left
		$node->textContent = preg_replace ('/(\w)\'(\w)/', '$1’$2', $node->textContent); // apostrophe
		$node->textContent = preg_replace ('/\'(.+?)\'/', '‘$1’', $node->textContent); // single quotes
	}

	// foreign languages use the <i> tag. Default for me is Hebrew
	protected function inlineItalic($excerpt){
		if (preg_match('#^\/(.+?)\/#', $excerpt['text'], $matches)) {
			return array(
				// How many characters to advance the Parsedown's
				// cursor after being done processing this tag.
				'extent' => strlen($matches[0]), 
				'element' => array(
					'name' => 'i',
					'handler' => array(
						'function' => 'lineElements',
						'argument' => $matches[1],
						'destination' => 'elements'
					),
					'attributes' => array('lang' => 'he')
				)
			);
		}
	}	// inlineItalic
	
	protected function inlineCite($excerpt){
		if (preg_match('#^_(.+?)_#', $excerpt['text'], $matches)) {
			return array(
				'extent' => strlen($matches[0]), 
				'element' => array(
					'name' => 'cite',
					'handler' => array(
						'function' => 'lineElements',
						'argument' => $matches[1],
						'destination' => 'elements'
					)
				)
			);
		}
	}	// inlineCite
	
	private $attributeRE = '#^{:(.+?)}\s*#';

	protected function inlineAttributes($excerpt){
		// based on https://python-markdown.github.io/extensions/attr_list/
		// but the {: attr list } goes before the element, not after
		if (preg_match($this->attributeRE, $excerpt['text'], $matches)) {
			return array(
				'extent' => strlen($matches[0]), 
				'element' => array(
					'name' => 'attr',
					'attributes' => self::parseAttributes($matches[1])
				)
			);
		}
	}	// inlineCite
	
	// allow for attribute lists before blocks; they should be in their own paragraph
	protected function paragraphContinue($Line, array $Block){
		if (!$Block['interrupted'] && preg_match ($this->attributeRE,  $Block['element']['handler']['argument'])){
			$Block['interrupted'] = 1;
		}
		return parent::paragraphContinue($Line, $Block);
	}

	// Sources use a footer in a block quote. Use -- to indicate this)
	protected function blockSource($Line, $Block = null){
		if ($Block && $Block['type'] === 'Paragraph'){
			// parse this immediately and add it to the paragraph (since paragraphs don't nest block-level elements)
			$Block['element']['handler']['argument'] .= "\n".$this->element($this->blockSource($Line)['element']);
			return $Block;
		}
		if (preg_match('/^--[ ]*(.+)/', $Line['text'], $matches)) {
			return array(
				'element' => array(
					'name' => 'footer',
					'handler' => array(
						'function' => 'lineElements',
						'argument' => $matches[1],
						'destination' => 'elements'
					),
					'attributes' => array('class' => 'source')
				)
			);
		}
	}	// blockSource

	protected function blockSourceContinue($Line, array $Block){
		if (!isset($Block['interrupted']) && preg_match('/^--[ ]*(.+)/', $Line['text'], $matches)){
			$Block['element']['handler']['argument'] .= $matches[1];
			return $Block;
		}
	}
	
} // KavanotParsedown
?>