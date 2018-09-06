<?php
/**
 * Created by PhpStorm.
 * User: frede
 * Date: 05/09/2018
 * Time: 13:07
 */

$baseUrl = "https://absalon.ku.dk/api/v1/";

function apiCall($method, $call)
{
	global $baseUrl, $session;
	
	$ch = curl_init($baseUrl . $call);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_COOKIE, "canvas_session=$session; path=/; domain=.absalon.ku.dk; Secure; HttpOnly;");
	$data = json_decode(str_replace("while(1);", "", curl_exec($ch)));
	return $data;
}

function downloadFile($fileLocation, $url)
{
	global $session;
	
	if (is_file(cleanPath($fileLocation))) return;
	
	$file = fopen(cleanPath($fileLocation), "w+");
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_COOKIE, "canvas_session=$session; path=/; domain=.absalon.ku.dk; Secure; HttpOnly;");
	curl_setopt($ch, CURLOPT_FILE, $file);
	curl_exec($ch);
	curl_close($ch);
	fclose($file);
}

function cleanPath($path)
{
	$path = preg_replace("/^C:/", "", $path);
	$path = preg_replace("/ ?[:]/", " -", $path);
	$path = preg_replace("/ ?\.\.\./", "", $path);
	return $path;
}

function cleanName($name) {
	$name = preg_replace("/\//", "-", $name);
	return $name;
}

function downloadAll($folder, $saveLocation)
{
	echo cleanPath($saveLocation) . "\n";
	
	createDir($saveLocation);
	
	$files = apiCall("GET", "folders/$folder/files");
	$folders = apiCall("GET", "folders/$folder/folders");
	
	foreach ($files as $file) {
		if ($file->locked_for_user)
			continue;
		$fileLocation = $saveLocation . cleanName($file->display_name);
		downloadFile($fileLocation, $file->url);
	}
	
	foreach ($folders as $folder) {
		downloadAll($folder->id, $saveLocation . $folder->name . "/");
	}
}

function getModuleItem($item, $location)
{
	switch ($item->type) {
		case "SubHeader":
			return $item->title;
		case "File":
			$id = $item->content_id;
			$file = apiCall("GET", "files/$id");
			downloadFile($location . cleanName($file->display_name), $file->url);
			return null;
		case "Assignment":
		case "Quiz":
		case "Page":
			createShortcut($location . cleanName($item->title), $item->html_url);
			return null;
		default:
			return null;
	}
}

function createShortcut($location, $url) {
	global $isUnix;
	file_put_contents(cleanPath(trim($location) . ($isUnix ? ".sh" : ".bat")), "start \"\" \"$url\"");
}

function createDir($dir) {
	if (!is_dir(cleanPath($dir)))
		mkdir(cleanPath($dir));
}