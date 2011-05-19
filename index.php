<?php
// measures XSS
header('Content-Type: text/html; charset=UTF-8');

// Handle
switch(true) {
	case isset($_GET['q'])                : echo json_encode(PointDetect($_GET['q'])); exit;
	case isset($_POST["tag"])             : header("Location: ./".$_POST["tag"]); exit;
	case $_GET["tag"]=="doc.html"         : echo file_get_contents("doc.html"); exit;
	case $_GET["tag"]==""                 : $_GET["tag"] = "eeis2011s";
	default :
		$start=microtime(true);
		if(preg_match("/^\\$/",$_GET["tag"])) {
			$kind = "$";
			$label = '$ingleText';
		} elseif(preg_match("/^\@/",$_GET["tag"])) {
			$kind = "@";
			$label = $_GET["tag"];
		} else {
			$kind = "#";
			$label = "#".$_GET["tag"];
		}
}

// Search Query
if($kind=="$") {
	$object = substr($_GET["tag"],1);
	$guess = PointDetect($object);
	$color = ColorSwitch($guess["points"]);
	$content .= '
		<table>
		<tr>
		<td class="head">
		noimage
		</td>
		<td class="body">
		'.htmlspecialchars($object, ENT_QUOTES, 'UTF-8').'<br>
		'.date("Y.m.d H:i:s",strtotime("now")).'<br>
		<span id="point" style="color:'.$color.';">Point: '.$guess['points'].'</span><br>
		</td>
		</tr>
		</table>
';
	$sum = array("point"=>$guess['points'],"times"=>1);
}else{
	$xml = simplexml_load_file("http://search.twitter.com/search.atom?rpp=100&".($kind=="@"?"from=".substr($_GET["tag"],1):"q=".$_GET["tag"]));
	$sum = array("point"=>0,"times"=>0);
	foreach($xml->entry as $val) {
		$val->title = htmlspecialchars($val->title, ENT_QUOTES, 'UTF-8');
		if(preg_match("/^RT/",$val->title)) continue;
		$guess = PointDetect($val->title);
		$color = ColorSwitch($guess['points']);
		$sum['point'] += $guess['points'];
		$sum['times'] ++;
		$content .= '
			<table>
			<tr>
			<td class="head">
			<a href="'.$val->author->uri.'"><img src="'.$val->link[1]->attributes()->href.'"></a>
			</td>
			<td class="body">
			'.htmlspecialchars($val->title, ENT_QUOTES, 'UTF-8').'<br>
			'.sprintf('%03d',$sum['times']).'
			: <a href="'.$val->link[0]->attributes()->href.'" target="_blank">'.date("Y.m.d H:i:s",strtotime($val->updated)).'</a>
			- <a href="'.$val->author->uri.'" target="_blank">'.$val->author->name.'</a><br>
			<span id="point" style="color:'.$color.';">Point: '.$guess['points'].'</span><br>
			</td>
			</tr>
			</table>';
			if(isset($_GET['debug'])) {
				$content .= "<p>";
				foreach($guess as $k=>$v) $content .= $k." : ".$v."<br>";
				$subject = str_replace("\\","#",(String)json_encode($val->title));
				preg_match_all("/#u([a-z0-9]{4,4})/",$subject,$matches);
				foreach($matches[1] as $v) $content.= hexdec($v)." ";
				$content .= "</p>";
			}
	}
}

function PointDetect($object) {
	$object = trim($object);
	$object = preg_replace("/#[a-zA-Z0-9-_]*/","",$object);
	$object = preg_replace("/RT[ @].*$/","",$object);
	$object = preg_replace("/@.*? /","",$object);
	$object = preg_replace("/https?:\/\/[\w-.!~*'\" ();\/?:@&=+\$,%\#]+/","",$object);
	$object = str_replace(array("\r","\n","\t"," ","　",",",".","、","。","，","．","!","?","！","？","・","…","ー","「","」","/"),"",$object);
	$object = preg_replace("/【.*?】/","",$object);
	$length = mb_strlen($object);
	$length = sprintf("%.2f",1+$length*($length/878));
	$kcodes = MatchCount($object,"/[一-龠|々]+/u");
	$kcodes = $kcodes*6.4/10;
	$ucodes = UnicodeDetect($object);
	$ucodes = sprintf("%.2f",$ucodes*0.000127);
	$nwords = WordDetect($object,array(
		"/リア充/u","/爆発/u","/死ぬ/u","/script/u","/だるい/u","/眠/u","/ねむい/u","/わろ/u","/つらい/u","/疲/u","/うける/u",
		"/帰/u","/バス/u","/(大丈夫.*?問題ない)/u","/(そんな.*?大丈夫か)/u","/[ｗw]{2}/u","/〜/u","/\([^一-龠ぁ-んァ-ヴー、。！？\!\?]+\)/u",));
	$nwords = $nwords*26.8;
	$pwords = WordDetect($object,array(
		"/環境情報/u","/SFC/u","/外資/u","/コンサル/u","/コンサルタント/u","/CSR/u","/読書/u","/研鑽/u","/投資/u","/学生団体/u",
		"/留学/u","/慶応/u","/慶應/u","/ベンチャー/u","/ビジネス/u","/インターン/u","/起業/u","/経済/u","/社会起業/u","/国際/u",
	));
	$pwords = $pwords*21.6;
	$points = $length * $ucodes + $kcodes - $nwords + $pwords;
	return array(
		"points"=>$points,
		"length"=>$length,
		"ucodes"=>$ucodes,
		"kcodes"=>$kcodes,
		"nwords"=>$nwords,
		"pwords"=>$pwords,
		"object"=>$object,
	);
}
function MatchCount($subject,$delimiter) {
	preg_match_all($delimiter,$subject,$match);
	$string = "";
	foreach($match as $value) $string.=implode($value);
	return mb_strlen($string);
}
function WordDetect($subject,$delimiters) {
	$words = 0;
	foreach($delimiters as $delimiter){
		preg_match_all($delimiter,$subject,$match);
		foreach($match as $val) $words+=count($val);
	}
	return $words;
}
function UnicodeDetect($subject){
	$subject = str_replace("\\","#",(String)json_encode($subject));
	preg_match_all("/#u([a-z0-9]{4,4})/",$subject,$matches);
	$max;
	$pre = 0;
	foreach($matches[1] as $key=>$value){
		$now = hexdec($value);
		if($max<$now) $max=$now;
		($pre!=$now) ? $sum += $now : $sum -= $now*1.809 ;
		$cnt = $key;
		$pre = $now;
	}
	return ($key!=0) ? ($sum/$key)+($max/0.987) : 2967.35905;
}
function ColorSwitch($points){
	switch(true){
		case $points<0   : $colors = "#882222"; break;
		case $points<180 : $colors = "#000000"; break;
		case $points<500 : $colors = "#228822"; break;
		default          : $colors = "#222288"; break;
	}
	return $colors;
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		*          { margin:0; padding:0; font-size:inherit; font-weight:inherit; font-family:inherit; }
		body       { margin:20px 50px; font:12px/1.2 courier,monospace; }
		h1         { font-weight:bold; font-size:18px; }
		h2         { font-weight:bold; font-size:18px; position:absolute; top:20px; right:50px; }
		h3         { font-size:10px; line-height:3.0; }
		a          { border:0; }
		table      { border:0; margin:50px 0; }
		input      { margin:4px; padding:2px; margin-left:0; }
		#point     { font-weight: bold; font-size: 20px; }
		.head      { padding-right:20px; vertical-align:top; }
	</style>
</head>
<body>
	<h1><?php echo "$label (".number_format(microtime(true)-$start,2)."s)"; ?></h1>
	<h3>nomark:hashtag @:openAccountName $:SingleText <a href="doc.html">Document</a></h3>
	<form action="./" method="POST">
		<input type="text" name="tag" value="<?php echo$_GET["tag"];?>">
		<input type="submit">
	</form>
	<?php echo $content; ?>
	<?php if($sum['point']==0){ ?>
	<h2>Failed.</h2>
	<?php }else{ $up = $sum['point']/$sum['times']; ?>
	<h2>ISHIKI: <span style="color:<?php echo ColorSwitch($up); ?>;"><?php echo $up; ?></span></h2>
	<?php } ?>
</body>
</html>
