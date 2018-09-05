<?php
/**
 * Created by PhpStorm.
 * User: frede
 * Date: 05/09/2018
 * Time: 13:07
 */

require_once "tools.php";

$config = json_decode(file_get_contents("config.json"));

if (!isset($config->canvas_session)) {
	echo "'canvas_session' missing in config.json";
	exit(1);
}
$session = $config->canvas_session;

if (!isset($config->save_location)) {
	echo "'save_location' missing in config.json";
	exit(1);
}
$baseSaveLocation = preg_replace("/\/*$/", "", $config->save_location) . "/";

if (!isset($config->unix)) {
	echo "'unix' missing in config.json";
	exit(1);
}
$isUnix = $config->unix == true;

$courseNames = [
	"Diskret matematik og algoritmer" => "DMA",
	"Programmering og problemløsning" => "PoP"
];

createDir($baseSaveLocation);

$courses = apiCall("GET", "courses");

foreach ($courses as $course) {
	$name = $course->name;
	foreach ($courseNames as $key => $courseName) {
		if (strpos($name, $key) !== false) {
			$name = $courseName;
			break;
		}
	}
	
	$id = $course->id;
	
	$root = apiCall("GET", "courses/$id/folders/root");
	
	$coursePath = $baseSaveLocation . cleanName($name) . "/";
	
	downloadAll($root->id, $coursePath);
	
	$modules = apiCall("GET", "courses/$id/modules");
	$modulePath = $coursePath . "#MODULES" . "/";
	createDir($modulePath);
	
	foreach ($modules as $module) {
		$moduleId = $module->id;
		$location = $modulePath . cleanName($module->name) . "/";
		
		createDir($location);
		
		$items = apiCall("GET", "courses/$id/modules/$moduleId/items");
		
		$header = null;
		foreach ($items as $item) {
			$tempHead = getModuleItem($item, $location . (isset($header) ? $header . "/" : ""));
			if (isset($tempHead)) {
				$header = $tempHead;
				createDir($location . $header);
			}
		}
	}
	
	$announcements = apiCall("GET", "announcements?context_codes[]=course_$id");
	$announcementPath = $coursePath . "#ANNOUNCEMENTS" . "/";
	createDir($announcementPath);
	
	foreach ($announcements as $announcement) {
		$time = new DateTime($announcement->posted_at);
		$fileName = $time->format("Y-m-d ") . cleanName($announcement->title) . ".html";
		$filePath = cleanPath($announcementPath . $fileName);
		if (!is_file($filePath))
			file_put_contents($filePath, $announcement->message);
	}
}

echo "Finished";