<?php

namespace Rana\RanaToolsBundle\Util;

/**
 * Utils class
 */
class CommandUtil
{
    public static function toUnderscore($input)
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }

    public static function readAndCloseFile($filePath)
    {
        $output = null;

        $file = fopen($filePath, 'r') or die('Unable to open '.$filePath.'!');
        $output = fread($file, filesize($filePath));
        fclose($file);
        
        return $output;
    }

    public static function writeAndCloseFile($filePath, $content)
    {
        $file = fopen($filePath, 'w') or die('Unable to create '.$filePath.'!');
        fwrite($file, $content);
        fclose($file);
    }

    public static function strReplace($text, $dataToReplace)
    {
        foreach($dataToReplace as $key => $value) {
            $text = str_replace($key, $value, $text);
        }
        return $text;
    }

    public static function getBundleAndEntityArray($bundleAndEntity, $output)
    {
        $bundleAndEntityArray = preg_split('/:+/', $bundleAndEntity);
        if (count($bundleAndEntityArray) != 2) {
            $output->writeln('<error>Incorrect Bundle:Entity argument, expecting something like AcmeBlogBundle:Blog</error>');
            exit();
        }

        foreach ($bundleAndEntityArray as &$element) {
            $element = ucfirst(trim($element));
        }
        
        return $bundleAndEntityArray;
    }
}
