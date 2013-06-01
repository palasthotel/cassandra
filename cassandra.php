<?php

if(count($argv)==1)
{
	echo "usage: cassandra --dbhost DBHOST --dbuser DBUSER --dbpass DBPASS --db DATABASE --baseurl BASEURL --authuser AUTHUSER --authpw AUTHPASSWORD\n";
	return;
}

$dbhost="localhost";
$dbuser="";
$dbpass="";
$db="";
$baseurl="http://localhost/";
$authenabled=FALSE;
$authuser="";
$authpw="";

for($i=1;$i<count($argv);$i++)
{
	if($argv[$i]=="--dbhost")
	{
		$i++;
		$dbhost=$argv[$i];
	}
	else if($argv[$i]=="--dbuser")
	{
		$i++;
		$dbuser=$argv[$i];
	}
	else if($argv[$i]=="--dbpass")
	{
		$i++;
		$dbuser=$argv[$i];
	}
	else if($argv[$i]=="--db")
	{
		$i++;
		$db=$argv[$i];
	}
	else if($argv[$i]=="--baseurl")
	{
		$i++;
		$baseurl=$argv[$i];
	}
	else if($argv[$i]=="--authuser")
	{
		$i++;
		$authuser=$argv[$i];
		$authenabled=TRUE;
	}
	else if($argv[$i]=="--authpw")
	{
		$i++;
		$authpw=$argv[$i];
		$authenabled=TRUE;
	}
}
echo "Fetching node ids...\n";
$database=new mysqli($dbhost,$dbuser,$dbpass,$db);
$database->set_charset("utf8");
$query=$database->query("select nid from node");
$nids=array();
while($row=$query->fetch_assoc())
{
	$nids[]=$row['nid'];
}
echo "done. now: fetch html and find links.\n";
$urls=array();
libxml_use_internal_errors(true);
for($i=0;$i<count($nids);$i++)
{
	$nid=$nids[$i];
	echo "\r$i/".count($nids)." (links until now: ".count($urls).")";
	$curl_query=curl_init($baseurl."node/$nid");
	curl_setopt($curl_query,CURLOPT_HEADER,FALSE);
	curl_setopt($curl_query,CURLOPT_RETURNTRANSFER,TRUE);
	if($authenabled)
	{
		curl_setopt($curl_query, CURLOPT_USERPWD, "$authuser:$authpw");
	}
	$html=curl_exec($curl_query);
	curl_close($curl_query);
	$doc=new DOMDocument();
	$doc->loadHTML($html);
	$elems=$doc->getElementsByTagName("a");
	foreach($elems as $elem)
	{
		if($elem->hasAttribute("href"))
		{
			$href=$elem->getAttribute("href");
			if(strpos($href, "/")===0)
			{
				$href=substr($href, 1);
			}
			if(!in_array($href, $urls))
			{
				if(strpos($href, $baseurl)===0)
				{
					$urls[]=$href;
				}
				else if(strpos($href, "http:")===FALSE)
				{
					$urls[]=$href;
				}
			}
		}
	}
}
echo "\nNow: check all found links against it's http status code\n";
$httpcodes=array();
for($i=0;$i<count($urls);$i++)
{
	echo "\r$i/".count($urls)." (status codes so far: ".implode(", ", array_keys($httpcodes)).")";
	$href=$urls[$i];
	if(strpos($href, $baseurl)===FALSE)
	{
		$href=$baseurl.$href;
	}
	$request=curl_init($href);
	curl_setopt($request, CURLOPT_RETURNTRANSFER, TRUE);
	if($authenabled)
	{
		curl_setopt($request, CURLOPT_USERPWD, "$authuser:$authpw");
	}
	curl_exec($request);
	$status=curl_getinfo($request,CURLINFO_HTTP_CODE);
	if(!array_key_exists($status, $httpcodes))
	{
		$httpcodes[$status]=array();
	}
	$httpcodes[$status][]=$href;
	curl_close($request);
}

echo "\nDone. Writing result out in cassandra.json\n";
file_put_contents("cassandra.json", json_encode($httpcodes));
echo "Cassandra is finished\n";
