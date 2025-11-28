<?php
namespace WP_AIE\helper;

/**
 * File System Helper Class
 */
class fs {

	public static function scan_directory( $dir) {
		$result = [];
		$files = scandir($dir);
		
		foreach ($files as $file) {
			$path = $dir . '/' . $file;
			if ($file !== '.' && $file !== '..' && ! is_file( $path )) {
				$item = [
					'name' => $file,
					'type' => is_dir($path) ? 'folder' : 'file',
					'path' => str_replace(ABSPATH, '', $path)
				];
				if (is_dir($path)) {
					$item['children'] = self::scan_directory($path);
				}
				$result[] = $item;
			}
		}
		return $result;
	}

}