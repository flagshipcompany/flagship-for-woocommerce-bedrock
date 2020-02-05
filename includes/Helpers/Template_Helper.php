<?php
namespace FlagshipWoocommerce\Helpers;

class Template_Helper {

	public static $placeholderPattern = '/{{(\s?)(%s)(\s?)}}/i';

    public static function render($filePath, $data) {
    	$content = file_get_contents(realpath(__DIR__ . '/../../templates').'/'.$filePath);
        $matched = preg_match_all(sprintf(self::$placeholderPattern, '\S+'), $content, $matches);

        if (!$matched) {
        	return $content;
        }

        foreach ($matches[0] as $key => $value) {
        	$content = self::replaceVar($content, $value, $data);
        }

        echo $content;
    }

    public static function replaceVar($content, $match, $data) {
	    $varName = preg_replace(sprintf(self::$placeholderPattern, '\S+'), '${2}', $match);

	    if (!$varName) {
	    	return $content;
	    }

	    if (!isset($data[$varName])) {
	    	throw new \Exception(sprintf('Variable %s is undefined!', $varName));
	    }

        return preg_replace(sprintf(self::$placeholderPattern, $varName), $data[$varName], $content);
    }
}