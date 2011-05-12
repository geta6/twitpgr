<?php
if(isset($_GET["sym"])&&isset($_GET["tag"])) {
	$query = "?".($_GET["sym"]=="#"?"q=".urlencode($_GET["sym"]):"from=").$_GET["tag"]."&rpp=100";
	$title = $_GET["sym"].$_GET["tag"];
	$selected = $_GET["sym"]=="#" ? array(" selected","") : array(""," selected") ;
	$entered = $_GET["tag"];
} else {
	$query = "?q=%23eeis2011s&rpp=100";
	$title = "#eeis2011s";
	$selected = array("","");
	$entered = "";
}
?>
<!DOCTYPE html>
<html>
<head>
	<style>
		*          { margin:0; padding:0; font: 12px/1.2 courier,monospace; }
		body       { margin-top: 80px; }
		form       { margin: 0 50px; }
		h1         { font-weight: bold; font-size: 18px; position: absolute; left: 50px; top: 10px; }
		h2         { position: fixed; right: 50px; top: 10px; }
		h2,h2 span { font-weight: bold; font-size: 18px;}
		p          { margin: 50px; }
		input      { margin:4px; padding:2px; margin-left:0; }
		#point     { font-weight: bold; font-size: 20px; }
	</style>
</head>
<body>
	<form action="ishiki.php" method="GET">
		<select name="sym">
			<option value="#"<?php echo$selected[0];?>>#</option>
			<option value="@"<?php echo$selected[1];?>>@</option>
		</select>
		<input type="text" name="tag" value="<?php echo$entered;?>">
		<input type="submit">
	</form>
<?php
$xml = simplexml_load_file("http://search.twitter.com/search.atom".$query);
$synth = 0;
foreach($xml->entry as $val) {
	$content = trim($val->title);
	$content = preg_replace("/#[a-zA-Z0-9-_]*/","",$content);
	$content = preg_replace("/RT.*?@.*$/","",$content);
	$content = preg_replace("/@.*? /","",$content);
	$content = preg_replace("/https?:\/\/[\w-.!~*'\" ();\/?:@&=+\$,%\#]+/","",$content);
	$content = str_replace(array("\r","\n","\t"," ","　",",",".","、","。","，","．","!","?","！","？","・","…"),"",$content);
	$length = mb_strlen($content);
	$kcodes = MatchCount($content,"/[一-龠|々]+/u");
	$nwords = WordDetect($content,array("/だるい/u","/ねむい/u","/つらい/u","/帰り/u","/バス/u","/(大丈夫.*?問題ない)/u","/(そんな.*?大丈夫か)/u","/[ｗw]/u"));
	$pwords = WordDetect($content,array("/環境情報/u","/SFC/u"));
	$points = $length*($length/100) + $kcodes*7.2 - $nwords*21.6 + $pwords*21.6;
	$synth += $points;
	echo "<p>\n";
	echo "\t".trim($val->title)."<br>\n";
	echo "\t".'<a href="'.$val->link[0]->attributes()->href.'" target="_blank">'.date("Y:m:d H:i:s",strtotime($val->updated)).'</a>';
	echo ' - ';
	echo '<a href="'.$val->author->uri.'" target="_blank">'.$val->author->name.'</a><br>'."\n";
	$col = ColorSwitch($points);
	echo "\t".'<span id="point" style="color:'.$col.';">Point: '.$points.'</span><br>'."\n";
	if(isset($_GET["debug"])){
		echo"抽出対象: ".$content."<br>";
		echo"文字列長点: ".($length*($length/100))."<br>";
		echo"漢字列数点: ".($kcodes*7.2)."<br>";
		echo"Ｎ文字列点: ".($nwords*21.6)." (減算)<br>";
		echo"Ｐ文字列点: ".($pwords*21.6)." (加算)<br>";
	}
	echo "</p>";
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
function ColorSwitch($value){
	switch(true){
	case $value<0   : $col = "#882222"; break;
	case $value<180 : $col = "#000000"; break;
	case $value<500 : $col = "#228822"; break;
	default         : $col = "#222288"; break;
	}
	return $col;
}
?>
<h1><?php echo $title; ?></h1>
<h2>意識の高さ平均: <span style="color:<?php echo ColorSwitch($synth/100); ?>;"><?php echo $synth/100; ?></span></h2>
</body>
</html>
