<?php
session_start();

require '../aws/aws-autoloader.php';
use Aws\S3\S3Client;  
use Aws\S3\Exception\S3Exception;

if($_SESSION['enable'])
{
	header('Location: ../index.php');
	exit;
}

$host = "boarddata.cchpc7kznfed.ap-northeast-1.rds.amazonaws.com";
$user = "root";
$password = "password";
$database = "boarddata";

$mysqli = new mysqli($host, $user, $password, $database);
if($mysqli->connect_errno)
{
	echo "DB接続失敗". $mysqli->connect_error;
}

$id = $_GET["deleteid"];
if(!$id)
{
	echo "IDが存在しません";
}

$redis = new Redis();
$redis->connect($_ENV['REDIS_HOST'],6379);

for($i = 1;$i <= $redis->dbsize(); $i++)
{
	$redisdata = $redis->hGetALL('datas'.$i);
	if($redisdata['id'] === $id)
	{
		$redis->del('datas'.$i);
		break;
	}
}

$image = $mysqli->query("SELECT * FROM datas WHERE id=$id AND image IS NOT NULL");

if($image)
{
	foreach($image as $row)
	{
		$filename = $row['image'];
	}

	if($filename || $filename <> "")
	{
		$s3 = new S3Client([
			'version' => 'latest',
 	  		'region'  => 'ap-northeast-1',
 	  		'credentials' => false,
		]);
		$bucket = 'webboarddatas';

		$result = $s3->deleteObject([
			'Bucket' => $bucket,
			'Key' => $filename,
		]);
	}
}

$data = $mysqli->prepare("DELETE FROM datas WHERE id = ?");
$data->bind_param('i', $id);
$data->execute();

?>

<!DOCTYPE html>
<head>
	<meta charset="UTF-8">
	<title>掲示板削除</title>
	<link href="style.css" rel="stylesheet">
</head>
<center>
<h1 class="title">掲示板</h1>
<section>
    <h2>削除完了</h2>
    <button class="button1" onclick="location.href='users.php'">戻る</button>
</section>
</center>