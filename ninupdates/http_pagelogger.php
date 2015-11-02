<?php

include_once("config.php");
include_once("logs.php");
include_once("db.php");

function init_curl_pagelogger()
{
	global $curl_handle_pagelogger, $sitecfg_workdir, $error_FH_pagelogger;

	$error_FH_pagelogger = fopen("$sitecfg_workdir/debuglogs/pagelogger_curlerror.log","w");
	$curl_handle_pagelogger = curl_init();
}

function close_curl_pagelogger()
{
	global $curl_handle_pagelogger, $error_FH_pagelogger;

	curl_close($curl_handle_pagelogger);
	fclose($error_FH_pagelogger);
}

function send_httprequest_pagelogger($url)
{
	global $httpstat_pagelogger, $sitecfg_workdir, $curl_handle_pagelogger, $error_FH_pagelogger, $lastmod_dateid, $lastmod;

	curl_setopt($curl_handle_pagelogger, CURLOPT_VERBOSE, true);
	curl_setopt ($curl_handle_pagelogger, CURLOPT_STDERR, $error_FH_pagelogger);

	curl_setopt($curl_handle_pagelogger, CURLOPT_RETURNTRANSFER, true);

	curl_setopt($curl_handle_pagelogger, CURLOPT_URL, $url);

	curl_setopt($curl_handle_pagelogger, CURLOPT_FILETIME, true);
	if(isset($lastdate))curl_setopt($ch, CURLOPT_HTTPHEADER, array("If-Modified-Since: " . gmdate('D, d M Y H:i:s \G\M\T', $lastdate)));

	curl_setopt($curl_handle_pagelogger, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($curl_handle_pagelogger, CURLOPT_SSL_VERIFYHOST, 0);

	$buf = curl_exec($curl_handle_pagelogger);

	$errorstr = "";

	$httpstat_pagelogger = curl_getinfo($curl_handle_pagelogger, CURLINFO_HTTP_CODE);
	if($buf===FALSE)
	{
		$errorstr = "HTTP request failed: " . curl_error ($curl_handle_pagelogger);
		$httpstat_pagelogger = "0";
	} else if($httpstat_pagelogger!="200")$errorstr = "HTTP error $httpstat_pagelogger: " . curl_error ($curl_handle_pagelogger);

	if($errorstr!="")$buf = $errorstr;

	$lastmod = curl_getinfo ($curl_handle_pagelogger, CURLINFO_FILETIME);
	echo "lastmod:".date(DATE_RFC822, $lastmod)."\n";
	$lastmod_dateid = date("m-d-y_H-i-s", $lastmod);
	echo "lastmod_dateid: $lastmod_dateid\n";

	return $buf;
}

function process_pagelogger($url, $datadir, $msgprefix, $msgurl, $enable_notification)
{
	global $httpstat_pagelogger, $lastmod_dateid, $lastmod;

	init_curl_pagelogger();
	$buf = send_httprequest_pagelogger($url);
	close_curl_pagelogger();

	if($httpstat_pagelogger!="200")
	{
		echo "Request for the pagelogger with url \"$url\" failed: HTTP $httpstat_pagelogger.\n";
		return 4;
	}

	$path = "$datadir/$lastmod_dateid";

	if(file_exists($path)===TRUE)return 0;//Already have this page revision.

	echo "This revision doesn't exist locally, saving it + sending notification...\n";

	$f = fopen($path, "w");
	fwrite($f, $buf);
	fclose($f);

	if($enable_notification==="1")
	{
		$msg = "$msgprefix Converted Last-Modified datetime: " . date(DATE_RFC822, $lastmod) . ". $msgurl";

		appendmsg_tofile($msg, "msg3dsdev");
	}
	else
	{
		echo "Notification sending is disabled.\n";
	}

	return 0;
}

?>