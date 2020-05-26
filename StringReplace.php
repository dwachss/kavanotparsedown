<?
namespace StringReplace;
// for a lot of the string manipulation that I do, I need to remove a section of the text, process the remainder, 
// and then put it back. I want to make sure that the placeholder is not something that might occur in the original text,
// so it is not replaced accidentally. It has to be legal XML, though. U+FFFC (https://en.wikipedia.org/wiki/Specials_(Unicode_block) ) 
// works for that. Removed strings are marked with  "￼{number}￼".
define ('OBJECT_REPLACEMENT_CHARACTER', '￼');
define ('RE_REPLACEMENT', '/'.OBJECT_REPLACEMENT_CHARACTER.'(\d+)'.OBJECT_REPLACEMENT_CHARACTER.'/');
$strings = array();
$reReplacement = '/'.OBJECT_REPLACEMENT_CHARACTER.'(\d+)'.OBJECT_REPLACEMENT_CHARACTER.'/';
$remover = function ($matches){
	global $strings;
	$strings []= $matches[0];
	return OBJECT_REPLACEMENT_CHARACTER.count($strings).OBJECT_REPLACEMENT_CHARACTER;
};
$replacer = function ($matches){
	global $strings;
	return $strings[$matches[1]-1];
};
function remove ($re, $target){
	global $remover;
	return preg_replace_callback ($re, $remover, $target);
}
function restore ($target){
	global $replacer;
	return preg_replace_callback (RE_REPLACEMENT, $replacer, $target);
}

?>