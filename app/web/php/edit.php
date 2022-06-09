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

$id = $_POST["getid"];
if(!$id)
{
	echo "IDが存在しません";
}

$data = $mysqli->query("SELECT * FROM datas WHERE id = $id");
if(!$data)
{
	echo "データが存在しません";
}

$name = $_POST['name'];
$message = $_POST['message'];
$delete = $_POST['delete'];
$image = $_FILES['image']['name'];

$redis = new Redis();
$redis->connect($_ENV['REDIS_HOST'],6379);

$bucket = 'webboarddatas';
$uploaddir = "s3://webboarddatas/";

if($image)
{
	$s3 = new S3Client([
		'version' => 'latest',
    	'region'  => 'ap-northeast-1',
    	'credentials' => false,
	]);
	$s3->registerStreamWrapper();

	$deleteimage = $mysqli->query("SELECT * FROM datas WHERE id=$id AND image IS NOT NULL");

	foreach($deleteimage as $row)
	{
		$filename = $row['image'];
			
		if($filename && $filename <> "")
		{	
			$result = $s3->deleteObject([
				'Bucket' => $bucket,
				'Key' => $filename,
			]);
		}
	}

	list($file_name, $file_type) = explode(".", $image);
	$ran = (string)random_int(0, 99999);
	$dateformat = date("Ymdhis");
	$hash = $name.$dateformat.$ran;
	$special = hash('sha1', $hash);

	$filepath = "$uploaddir$special.$file_type";
	$image = "$special.$file_type";

	$imagedata = file_get_contents($_FILES['image']['tmp_name']);
	if(!file_put_contents($filepath, $imagedata)) { echo "s3アップロード失敗"; }

	for($i = 1;$i <= $redis->dbsize(); $i++)
	{
		$redisdata = $redis->hGetALL('datas'.$i);
		if($redisdata['id'] === $id)
		{
			$redis->hSet('datas'.$i,'name',$name);
			$redis->hSet('datas'.$i,'message',$message);
			$redis->hSet('datas'.$i,'image',$image);
			break;
		}
	}

	$data = $mysqli->query("UPDATE datas SET name='$name',message='$message',image='$image' WHERE id = $id");
}
else if($delete)
{
	$deleteimage = $mysqli->query("SELECT * FROM datas WHERE id=$id");
	foreach($deleteimage as $row)
	{
		$filename = $row['image'];

		$s3 = new S3Client([
			'version' => 'latest',
    		'region'  => 'ap-northeast-1',
    		'credentials' => false,
		]);

		$result = $s3->deleteObject([
			'Bucket' => $bucket,
			'Key' => $filename,
		]);	
	}

	for($i = 1;$i <= $redis->dbsize(); $i++)
	{
		$redisdata = $redis->hGetALL('datas'.$i);
		if($redisdata['id'] === $id)
		{
			$redis->hSet('datas'.$i,'name',$name);
			$redis->hSet('datas'.$i,'message',$message);
			$redis->hSet('datas'.$i,'image',NULL);
			break;
		}
	}

	$data = $mysqli->query("UPDATE datas SET name='$name',message='$message',image=NULL WHERE id = $id");
}
else
{
	for($i = 1;$i <= $redis->dbsize(); $i++)
	{
		$redisdata = $redis->hGetALL('datas'.$i);
		if($redisdata['id'] === $id)
		{
			$redis->hSet('datas'.$i,'name',$name);
			$redis->hSet('datas'.$i,'message',$message);
			break;
		}
	}

	$data = $mysqli->query("UPDATE datas SET name='$name',message='$message' WHERE id = $id");
}
?>

<!DOCTYPE html>
<head>
	<meta charset="UTF-8">
	<title>掲示板編集</title>
	<link href="style.css" rel="stylesheet">
</head>
<center>
<h1 class="title">掲示板</h1>
<section>
    <h2>編集完了</h2>
    <button class="button1" onclick="location.href='users.php'">戻る</button>
</section>
</center>
