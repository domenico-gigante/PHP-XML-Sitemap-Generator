<?php
/*************************************************************
 ReloadLab PHP XML Sitemap Generator
 Sviluppata sulla base di iProDev PHP XML Sitemap Generator
 Simple site crawler to create a search engine XML Sitemap.
 Version 1.0
 Free to use, without any warranty.
 Written by Reload(Domenico Gigante) https://www.reloadlab.it 24/Feb/2021.

*************************************************************/

ini_set('memory_limit', '4G');
set_time_limit(0);

// version 1.7 o 1.9, if PHP 5.6+ is available
require_once('simple_html_dom.php');

// Set true or false to define how the script is used.
// true:  As CLI script.
// false: As Website script.
define('CLI', true);

define('VERSION', '1.0');                                            
define('NL', CLI? "\n": '<br>');

// Set the output file name.
$file = 'sitemap.xml';

// Set the start URL. Here is http used, use https:// for 
// SSL websites.
$start_url = 'https://www.mefop.it/';       

// Define here the URLs to skip. All URLs that start with 
// the defined URL will be skipped too.
// Example: 'http://iprodev.com/print' will also skip
// http://iprodev.com/print/bootmanager.html
$skip = array(
	//
);

// Define what file types should be scanned.
$extension = array(
	'.html', 
	'.php',
	'/',
); 

// Scan frequency
$freq = 'weekly';

// Page priority
$priority = '1';

// numero di url scansionate prima di scriverle sul file xml
$max_url_write = 100;

// numero di sitemap da conservare prima di cancellare
$num_rotation = 4;

// debug 
$debug = 0;

// start xml
$start_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
	"<?xml-stylesheet type=\"text/xsl\" href=\"http://iprodev.github.io/PHP-XML-Sitemap-Generator/xml-sitemap.xsl\"?>\n".
	"<!-- Created with iProDev PHP XML Sitemap Generator ".VERSION." http://iprodev.com -->\n".
	"<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"\n".
	"        xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\n".
	"        xsi:schemaLocation=\"http://www.sitemaps.org/schemas/sitemap/0.9\n".
	"        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd\">\n".
	"  <url>\n".
	"    <loc>".htmlentities($start_url)."</loc>\n".
	"    <changefreq>$freq</changefreq>\n".
	"    <priority>$priority</priority>\n".
	"  </url>\n";

// end xml
$end_xml = "</urlset>\n";

// Init end ==========================

function rel2abs($rel, $base)
{
	if(strpos($rel, '//') === 0){
		
		return 'http:'.$rel;
	}
	
	/* return if already absolute URL */
	if(parse_url($rel, PHP_URL_SCHEME) != ''){
		
		return $rel;
	}
	
	$first_char = substr ($rel, 0, 1);

	/* queries and anchors */
	if($first_char == '#' || $first_char == '?'){
		
		return $base.$rel;
	}

	/* parse base URL and convert to local variables:
	$scheme, $host,  $path */
	extract(parse_url($base));

	/* remove non-directory element from path */
	$path = preg_replace('#/[^/]*$#', '', $path);

	/* destroy path if relative url points to root */
	if($first_char ==  '/'){
		
		$path = '';
	}
	
	/* dirty absolute URL */
	$abs =  $host.$path.'/'.$rel;
	
	/* replace '//' or '/./' or '/foo/../' with '/' */
	$re =  array('#(/.?/)#', '#/(?!..)[^/]+/../#');
	for($n = 1; $n > 0;  $abs = preg_replace($re, '/', $abs, -1, $n)){
		
	}
	
	/* absolute URL is ready! */
	return  $scheme.'://'.$abs;
}

function GetUrl($url)
{
	global $debug;
	
	$agent = 'Mozilla/5.0 (compatible; iProDev PHP XML Sitemap Generator/'.VERSION.', http://iprodev.com)';

	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_USERAGENT, $agent);
	curl_setopt($ch, CURLOPT_VERBOSE, $debug);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	$data = curl_exec($ch);

	curl_close($ch);

	return $data;
}

function Scan($url, &$count_url, &$str_xml)
{
	global $file, $start_url, $max_url_write, $end_xml, $scanned, $extension, $skip, $freq, $priority;
	
	if($count_url == 0){
		
		$str_xml = '';
	}

	echo $url.NL;

	$url = filter_var($url, FILTER_SANITIZE_URL);
	
	$key_url = str_replace($start_url, '/', $url);

	if(!filter_var($url, FILTER_VALIDATE_URL) || isset($scanned[$key_url])){

		return;
	}

	$scanned[$key_url] = true;
	
	if($html = str_get_html(GetUrl($url))){
		
		$a1 = $html->find('a');
		
		$html->clear(); 
		unset($html);
	
		foreach($a1 as $val){
			
			$next_url = $val->href or '';
	
			$fragment_split = explode('#', $next_url);
			$next_url = $fragment_split[0];
	
			if((substr($next_url, 0, 7) != 'http://') && 
				(substr($next_url, 0, 8) != 'https://') &&
				(substr($next_url, 0, 6) != 'ftp://') &&
				(substr($next_url, 0, 7) != 'mailto:')){
				
				$next_url = @rel2abs($next_url, $url);
			}
	
			$next_url = filter_var($next_url, FILTER_SANITIZE_URL);
			
			$next_key_url = str_replace($start_url, '/', $next_url);
	
			if(substr($next_url, 0, strlen($start_url)) == $start_url){
				
				$ignore = false;
	
				if(!filter_var($next_url, FILTER_VALIDATE_URL)){
					
					$ignore = true;
				}
	
				if(isset($scanned[$next_key_url])){
					
					$ignore = true;
				}
	
				if(isset($skip) && !$ignore){
					
					foreach($skip as $v){
						
						if(substr($next_url, 0, strlen($v)) == $v){
							
							$ignore = true;
						}
					}
				}
				
				if(!$ignore){
					
					foreach($extension as $ext){
						
						if(strrpos($next_url, $ext) > 0){
							
							$pr = number_format(round($priority / count(explode('/', trim(str_ireplace(array('http://', 'https://'), '', $next_url), '/'))) + 0.5, 3), 1);
							
							$str_xml .= "  <url>\n".
								"    <loc>".htmlentities($next_url)."</loc>\n".
								"    <changefreq>$freq</changefreq>\n".
								"    <priority>$pr</priority>\n".
								"  </url>\n";
								
							$count_url++;
							
							if($count_url >= $max_url_write){
								
								if(smwrite($file, $str_xml.$end_xml)){
									
									$count_url = 0;
								}
							}
							
							Scan($next_url, $count_url, $str_xml);
						}
					}
				}
			}
		}
	}
}

function trimfile($filename)
{ 
	// File size
	$filesize = filesize($filename);

	if(!$filesize){
		
		return '';
	}
	
	// Open file
	$file_handle = fopen($filename, 'r');
	
	// Set up loop variables
	$linebreak  = false;
	$file_start = false;

	// Number of bytes to look at
	$bite = 50;
	
	// Put pointer to the end of the file.
	fseek($file_handle, 0, SEEK_END);
 
	while($linebreak === false && $file_start === false){

		// Get the current file position.
		$pos = ftell($file_handle);
	 
		if($pos < $bite){
			
			// If the position is less than a bite then go to the start of the file
			rewind($file_handle);
		} else{ 
			
			// Move back $bite characters into the file
			fseek($file_handle, -$bite, SEEK_CUR);
		}
	 
		// Read $bite characters of the file into a string.
		$string = fread($file_handle, $bite) or die('Can\'t read from file '.$filename.'.');
	 
		/* If we happen to have read to the end of the file then we need to ignore 
		 * the lastfile line as this will be a new line character.
		 */
		if($pos + $bite >= $filesize){
			
			$string = substr_replace($string, '', -1);
		}
	 
		// Since we fread() forward into the file we need to back up $bite characters. 
		if($pos < $bite){
			
			// If the position is less than a bite then go to the start of the file
			rewind($file_handle);
		} else{ 
			
			// Move back $bite characters into the file
			fseek($file_handle, -$bite, SEEK_CUR);
		}
	 
		// Is there a line break in the string we read?
		if(is_integer($lb = strrpos($string, "\n"))){
			
			// Set $linebreak to true so that we break out of the loop
			$linebreak = true;
			
			// The last line in the file is right after the linebreak
			$line_end = ftell($file_handle) + $lb + 1; 
		}
	 
		if(ftell($file_handle) == 0){
			
			// Break out of the loop if we are at the beginning of the file. 
			$file_start = true;
		}
	}

	if($linebreak === true){

		// If we have found a line break then read the file into a string to writing without the last line.
		rewind($file_handle);
		
		$file_minus_lastline = fread($file_handle, $line_end - 1);
		
		fclose($file_handle);

		return $file_minus_lastline;
	}
	
	$content = fread($file_handle, $filesize);
	
	fclose($file_handle);
	
	return $content;
}

function smwrite($file, $str, $start_new_sitemap = false)
{
	if($start_new_sitemap){
		
		$newstr = '';
	} else{
		
		$newstr = trimfile($file).NL;
	}
	
	$pf = fopen($file, 'w+');
	if(!$pf){
		
		echo 'Cannot create '.$file.'!'.NL;
		return false;
	}
	
	fwrite($pf, $newstr.$str);

	fclose($pf);
	
	return true;
}

function rotate($sitemap)
{	
	global $num_rotation;
	
	if($num_rotation <= 0){
		
		return;
	}
	
	$fileremove = '';
	$lastfile = date('Ymd');
	$num = 0;
	
	$info_sm = explode('.', $sitemap);
	$ext_sm = array_pop($info_sm);
	$filename_sm = implode('.', $info_sm);
	
	$cur_dir = rtrim(getcwd(), '/').'/';
	$files = array_diff(scandir($cur_dir), array('.', '..'));
	
	if($files){
		
		foreach($files as $file){
			
			if(strrpos($file, $ext_sm) > 0){
				
				if($file == $sitemap){
					
					$filename = $filename_sm.'.'.date('Ymd').'.'.$ext_sm;
					@rename($cur_dir.$sitemap, $cur_dir.$filename);
					$num++;
				} else{
				
					$info = explode('.', $file);
					$ext = array_pop($info);
					$timestamp = array_pop($info);
					$filename = implode('.', $info);
					
					if($filename_sm == $filename && is_numeric($timestamp)){
						
						if((int) $timestamp < (int) $lastfile){
							
							$lastfile = $timestamp;
							$fileremove = $file;
						}
						
						$num++;
					}
				}
			}
		}
		
		if($fileremove != '' && $num >= $num_rotation){
			
			@unlink($cur_dir.$fileremove);
		}
	}
}

rotate($file);

if(smwrite($file, $start_xml.$end_xml, true)){

	$start_url = filter_var($start_url, FILTER_SANITIZE_URL);
	
	$scanned = array();
	
	$count_url = 0;
	$str_xml = 0;
	
	Scan($start_url, $count_url, $str_xml);
	
	if($count_url > 0 && $count_url < $max_url_write){
		
		smwrite($file, $str_xml.$end_xml);
	}
}

echo 'Done.'.NL;
echo $file.' created.'.NL;