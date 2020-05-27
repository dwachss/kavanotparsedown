<?
require_once ('Parsedown.php');
require_once ('StringReplace.php');

class KavanotParsedown extends Parsedown {

	protected $reItalic = '#^\/(.+?)\/#';
	protected $reCite = '#^_(.+?)_#';
	protected $reAttributes = '#^{:(.+?)}\s*#';
	protected $reSource = '/^--[ ]*(.+)/';
	protected $reSmartQuotes = array(
		'"..."' => 'adjustEllipsis',
		'"--"' => 'adjustEmDash',
		"'\"'" => 'adjustDoubleQuotes',
		'"\'"' => 'adjustSingleQuotes'
	);
		

	function __construct(){
		$this->InlineTypes['/'] []= 'Italic';
		$this->InlineTypes['_'] = ['Cite']; // redefinition
		$this->InlineTypes['{'] []= 'Attributes';
		$this->inlineMarkerList = implode ('', array_keys($this->InlineTypes));
		$this->specialCharacters []= '/'; // only characters in this array can be escaped by '\'
		$this->BlockTypes['-'][] = 'Source';
	}
	
	function text($text){
		$text = parent::text($text);
		
		// manipulate the elements by turning them into a real DOMDocument
		// https://stackoverflow.com/a/51666821 to ignore errors
		// https://stackoverflow.com/a/16931835 to use UTF-8
		// https://stackoverflow.com/a/22490902 to not add DTD
		// https://stackoverflow.com/a/29499398 to wrap it in an <div> element (since it needs a single root)
		$dom = DOMDocument::loadHTML('<?xml encoding="UTF-8"><div>'.$text.'</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_HTML_NODEFDTD);
		$xpath = new DOMXpath($dom);
		
		// Source citations use <footer class=source> for the citations. Adjust those to match whatwg recommendations
		foreach ($xpath->query("//footer[@class='source']") as $footer) self::adjustFooter($footer);
		
		// turn <attr> elements into attributes of the following element
		foreach ($xpath->query("//attr") as $attr) self::moveAttributes ($attr);
		
		// process elements with the markdown attribute
		foreach ($xpath->query("//*[@markdown] | //*[@md]") as $element) $this->processElement ($element);
				
		$text = $dom->saveHTML($dom->documentElement);

		// smart quotes etc.
		$text = $this->removeInlineTags($text);		
		foreach ($this->reSmartQuotes as $plain => $fancy){
			$text = $this->$fancy ($text);
		}

		// undo all the string removal
		$text = StringReplace\restore ($text);
		
		// remove the nonsemantic <div>
		$text = preg_replace ('#^<div>|</div>$#', '', $text);

		return $text;
	} // text
	
//----- Italic ------
	// foreign languages use the <i> tag. Default for me is Hebrew
	protected function inlineItalic($excerpt){
		if (preg_match($this->reItalic, $excerpt['text'], $matches)) {
			return array(
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
	
//----- Cite ----
	protected function inlineCite($excerpt){
		if (preg_match($this->reCite, $excerpt['text'], $matches)) {
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

//---- Attributes -----
	protected function inlineAttributes($excerpt){
		// based on https://python-markdown.github.io/extensions/attr_list/
		// but the {: attr list } goes before the element, not after
		if (preg_match($this->reAttributes, $excerpt['text'], $matches)) {
			return array(
				'extent' => strlen($matches[0]), 
				'element' => array(
					'name' => 'attr',
					'attributes' => $this->parseAttributes($matches[1])
				)
			);
		}
	}	// inlineAttributes
	
	// allow for attribute lists before blocks; they should be in their own paragraph
	protected function paragraphContinue($Line, array $Block){
		if (!$Block['interrupted'] && preg_match ($this->reAttributes,  $Block['element']['handler']['argument'])){
			$Block['interrupted'] = 1;
		}
		return parent::paragraphContinue($Line, $Block);
	}
	
//---- Source -----
	protected function blockSource($Line, $Block = null){
		if (preg_match('/^--[ ]*(.+)/', $Line['text'], $matches)) {
			if ($Block && $Block['type'] === 'Paragraph'){
				// parse this immediately and add it to the paragraph (since paragraphs don't nest block-level elements)
				$Block['element']['handler']['argument'] .= "\n".$this->element($this->blockSource($Line)['element']);
				return $Block;
			}
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

//---- Markdown inside HTML blocks
	protected function processElement($e){
		$outerHTML = $e->ownerDocument->saveHTML($e);
		$innerHTML = preg_replace ('#(^[^>]*>)|(<[^<]*$)#', '', $outerHTML);
		$innerHTML = $this->text ($innerHTML);
		$replacementString = StringReplace\remove ('/^.*$/s', $innerHTML); // remove the entire innerHTML
		$e->removeAttribute ('markdown');
		$e->removeAttribute ('md');
		$e->textContent = $replacementString;
	}

//---- Smart Quotes -----
	protected function adjustEllipsis ($text){
		return $this->preg_replace_text ('/\.\.\./', '…', $text);
	}

	protected function adjustEmDash ($text){
		return $this->preg_replace_text ('/--/', '—', $text);
	}
	
	protected function adjustDoubleQuotes ($text){
		$gershayim = '״'; // these are very hard to see in my editor, so I separated these out
		$hebrew = '[א-ת]';
		$text = $this->preg_replace_text ("/($hebrew)\"($hebrew)/", "$1$gershayim$2", $text);
		$text = $this->preg_replace_text ("/($hebrew)(\W*)\"(.+?)\"/", '$1$2”$3“', $text);  // if preceded by Hebrew, assume it's right to left
		$text = $this->preg_replace_text ('/"(.+?)"/', '“$1”', $text);
		return $text;
	}

	protected function adjustSingleQuotes ($text){
		$geresh = '׳'; // these are very hard to see in my editor, so I separated these out
		$hebrew = '[א-ת]';
		$text = $this->preg_replace_text ("/($hebrew)'(\\W)/", "$1$geresh$2", $text);
		// no single quotes in Hebrew; assume that we only want the geresh.
		$text = $this->preg_replace_text ('/(\w)\'(\w)/', '$1’$2', $text); // apostrophe
		$text = $this->preg_replace_text ('/\'(.+?)\'/', '‘$1’', $text); // single quotes
		return $text;
	}

//---- Utility functions for Attributes ----
	protected function parseAttributes ($attrString){
		// shortcuts:
		// #string is translated to id="string"
		// .string is translated to class="string"
		// [a-zA-Z]{2} is translated to lang=[a-zA-Z]{2} (since I use the lang= attribute so much)
		$attrString = " $attrString "; // to simplify the regex, search for space-delimited rather than start/end
		// to make this work, we need to actually parse the string; simply splitting on spaces won't account for strings
		// so we pull out quotes. Fortunately HTML doesn't escape quotes; you need to use &quot;
		$attrString = StringReplace\remove ('/("[^"]*")|(\'[^\']*\')/', $attrString);
		$attrString = preg_replace ('/ #(\w+)(?= )/', ' id=$1 ', $attrString);
		$attrString = preg_replace ('/ \.(\w+)(?= )/', ' class=$1 ', $attrString);
		$attrString = preg_replace ('/ ([a-zA-Z]{2})(?= )/', ' lang=$1 ', $attrString);
		$attrString = StringReplace\restore ($attrString);
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

	static protected function copyAttributes ($from, $to){
		try{
			foreach ($from->attributes as $attr) $to->setAttribute($attr->nodeName, $attr->nodeValue);
		}catch(Exception $e){
			// ignore errors; generally are illegal characters in the attribute name
		}
	}

//---- utility functions for Source	
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
	
//---- String utilities
	protected function removeInlineTags ($text){
		$reInlineTag = '#</?('.implode('|',$this->textLevelElements).')\b[^>]*>#';
		return StringReplace\remove ($reInlineTag, $text);
	}

	protected function preg_replace_text ($pattern, $replacement, $subject){
		// only replace text that is not inside tags. Assumes $subject is valid HTML and surrounded by tags
		// the (?<=>) is a lookbehind assertion to make sure the match starts with a >
		// so the regex matches any text of the form >...< and then searches for $pattern in that
		return preg_replace_callback('/(?<=>)[^<]*/', function($matches) use ($pattern, $replacement) {
			return preg_replace($pattern, $replacement, $matches[0]);
		}, $subject);
	}
} // KavanotParsedown
?>