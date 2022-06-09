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

$accid = $_SESSION['accountid'];

$name = $_POST["name"];
if($accid) { $name = $_SESSION['username']; }
$comment = $_POST["comment"];
$filename = $_FILES['image']['name'];

date_default_timezone_set("Asia/Tokyo");
$posttime = date("Y-m-d H:i:s");

$bucket = 'webboarddatas';
$uploaddir = "s3://webboarddatas/";
$imagepath = "https://webboarddatas.s3.ap-northeast-1.amazonaws.com/";

if(!empty($comment))
{
	if($filename && $filename <> "")
	{
		$s3 = new S3Client([
			'version' => 'latest',
    		'region'  => 'ap-northeast-1',
    		'credentials' => false,
		]);
		$s3->registerStreamWrapper();

		list($file_name, $file_type) = explode(".", $filename);

		$ran = (string)random_int(0, 99999);
		$dateformat = date("Ymdhis");
		$hash = $name.$dateformat.$ran;
		$special = hash('sha1', $hash);

		$filepath = "$uploaddir$special.$file_type";
		$filename = "$special.$file_type";
		
		$imagedata = file_get_contents($_FILES['image']['tmp_name']);
		if(!file_put_contents($filepath, $imagedata)) { echo "s3アップロード失敗"; }
	}
	else { $filename = NULL; }

	if($accid)
	{
		$data = $mysqli->query("INSERT INTO datas(message,posttime,accountid,image) VALUES('$comment','$posttime','$accid','$filename')");
	}
	else
	{
		$data = $mysqli->query("INSERT INTO datas(name,message,posttime,image) VALUES('(G)$name','$comment','$posttime','$filename')");
	}
}

$redis = new Redis();
$redis->connect($_ENV['REDIS_HOST'],6379);

$datacount = 0;
$cacheIsExist = false;

$count = $mysqli->query("SELECT COUNT(id) FROM datas");
$rows = mysqli_fetch_array($count, MYSQLI_NUM);

$redisdata = $redis->hGetALL('datas1');
if(empty($redisdata) || $redis->dbsize() !== $rows[0])
{
	if(!empty($redisdata))
	{
		$count = 1;
		$bool = $redis->del('datas'.$count);
		while($bool > 0)
		{
			$count++;
			$bool = $redis->del('datas'.$count);
		}
	}

	$data = $mysqli->query("SELECT * FROM datas order by posttime desc");
	if(!$data)
	{
		echo "データテーブルが存在しない。";
	}

	foreach($data as $row)
	{
		$cache = array(
			'id' => $row['id'],
			'name' => $row['name'],
			'message' => $row['message'],
			'posttime' => $row['posttime'],
			'accountid' => $row['accountid'],
			'image' => $row['image']
		);

		$datacount++;
		$redis->hMSet('datas'.$datacount, $cache);
	}
	$cacheIsExist = false;
}
else
{
	$cacheIsExist = true;
}

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
	header('Location: users.php');
	exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>掲示板<?php if(!$accid) { echo "(ゲスト)"; } ?></title>
	<link href="style.css" rel="stylesheet">
</head>
<body>
	<button onclick="location.href='../index.php'" class="return">←ログイン画面に戻る</button>
	<center>
	<h1 class="title">掲示板<?php if(!$accid) { echo "(ゲスト)"; } ?></h1>
	<section>
		<h2>投稿</h2>
		<form action="users.php" method="post" enctype="multipart/form-data">
			<?php if($accid) { ?>
			<p name="name" class="namebox" id="names">名前：<?php echo $_SESSION['username']?></p><br>
		<?php } else { ?>
			名前：<input type="text" name="name" class="namebox" id="names" required><br>
		<?php } ?>
			投稿内容：<button type="submit" id="send" class="button0">投稿</button><br>
			<textarea type="text" name="comment" class="textbox" id="messages" required></textarea><br>
			画像：<input type="file" name="image" accept="image/*">
		</form>
	</section>
	</center>
		<br><hr style="height: 2px; background-color: black;">
	<section>
		<h2 align="center">投稿内容一覧</h2>

		<?php if($cacheIsExist) { ?>
			<?php for($i = 1;$i <= $datacount; $i++) {

				$redisdata = $redis->hGetALL('datas'.$i); ?>

				<div class="comment">
					<?php if(!$redisdata['accountid']) {?>
						<p class="commentname">名前 : <?php echo $redisdata['name']?></p>

					<?php } else {
							$commentcaaid = $redisdata['accountid'];
							$account = $mysqli->query("SELECT * FROM accounts WHERE accountid = '$commentcaaid'");
							$rows = mysqli_fetch_array($account, MYSQLI_ASSOC); ?>
							<p class="commentname">名前 : <?php echo $rows['username']?></p>
					<?php }?>
					<p class="commenttime">時刻 : <?php echo $redisdata['posttime']?></p>
					<p class="info">投稿内容 : <br><?php echo $redisdata['message']?></p>
					<?php if($redisdata['image']) { ?>
					<img class="resize" src="<?php echo $imagepath.$redisdata['image']; ?>"><?php } ?>

					<?php if($_SESSION['accountid'] AND ($_SESSION['Developer'] === $redisdata['lv'] OR $_SESSION['accountid'] === $redisdata['accountid'])) { ?>
					<div class="display">
					<form action="editing.php" method="get" class="from">
						<input type="hidden" name="editid" value="<?php echo $redisdata['id']?>">
						<button type="submit" class="button1">編集</button>
					</form>
					<form action="delete.php" method="get" class="from">
						<input type="hidden" name="deleteid" value="<?php echo $redisdata['id']?>">
						<button type="submit" class="button1">削除</button>
					</form>
					<?php if($redisdata['image']) { ?>
					<form action="imagedelete.php" method="get" class="from">
						<button type="submit" name="imageid" class="button1" value="<?php echo $redisdata['id']?>">画像削除</button>
					</form>
					<?php } ?>
					</div>
					<?php }?>
				</div>
			<?php }?>
		<?php } else { ?>
				<?php foreach($data as $row):?>

				<div class="comment">
					<?php if(!$row['accountid']) {?>
						<p class="commentname">名前 : <?php echo $row['name']?></p>

					<?php } else {
							$commentcaaid = $row['accountid'];
							$account = $mysqli->query("SELECT * FROM accounts WHERE accountid = '$commentcaaid'");
							$rows = mysqli_fetch_array($account, MYSQLI_ASSOC); ?>
							<p class="commentname">名前 : <?php echo $rows['username']?></p>
					<?php }?>
					<p class="commenttime">時刻 : <?php echo $row['posttime']?></p>
					<p class="info">投稿内容 : <br><?php echo $row['message']?></p>
					<?php if($row['image']) { ?>
					<img class="resize" src="<?php echo $imagepath.$row['image']; ?>"><?php }?>

					<?php if($_SESSION['Developer'] === $row['lv'] OR $_SESSION['accountid'] === $row['accountid']) { ?>
					<div class="display">
					<form action="editing.php" method="get" class="from">
						<input type="hidden" name="editid" value="<?php echo $row['id']?>">
						<button type="submit" class="button1">編集</button>
					</form>
					<form action="delete.php" method="get" class="from">
						<input type="hidden" name="deleteid" value="<?php echo $row['id']?>">
						<button type="submit" class="button1">削除</button>
					</form>
					<?php if($row['image']) { ?>
					<form action="imagedelete.php" method="get" class="from">
						<button type="submit" name="imageid" class="button1" value="<?php echo $row['id']?>">画像削除</button>
					</form>
					<?php } ?>
					</div>
					<?php }?>
				</div>
			<?php endforeach; ?>
		<?php } ?>

	</section>

</body>
</html>