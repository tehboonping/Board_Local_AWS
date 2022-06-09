<?php
session_start();

if($_SESSION['enable'])
{
	header('Location: ../index.php');
	exit;
}

$developerid = '1';

$host = "boarddata.cchpc7kznfed.ap-northeast-1.rds.amazonaws.com";
$user = "root";
$password = "password";
$database = "boarddata";

$mysqli = new mysqli($host, $user, $password, $database);
if($mysqli->connect_errno)
{
	echo "DB接続失敗". $mysqli->connect_error;
}

if(!$_GET["editid"])
{
	echo "IDが存在しません";
}
$id = $_GET["editid"];

$data = $mysqli->query("SELECT * FROM datas WHERE id = $id");

if(!$data)
{
	echo "データが存在しません";
}
?>

<!DOCTYPE html>
<meta charset="UTF-8">
<head>
	<meta charset="UTF-8">
	<title>掲示板編集</title>
	<link href="style.css" rel="stylesheet">
</head>
<h1 class="title">掲示板</h1>
<section>
    <h2>投稿の編集</h2>
    <form action="edit.php" method="post" enctype="multipart/form-data">
    	<h3>名前</h3>
    	<?php foreach($data as $row):?>
    		<input type="hidden" name="getid" value="<?php echo $id?>">
    		<?php if($_SESSION['Developer'] === $developerid) {?>
    			<?php if(!$row['accountid']) {?>
					<input name="name" class="namebox" value="<?php echo $row['name']?>" readonly>
				<?php } else {
						$commentcaaid = $row['accountid'];
						$account = $mysqli->query("SELECT * FROM accounts WHERE accountid = '$commentcaaid'");
						$rows = mysqli_fetch_array($account, MYSQLI_ASSOC);
						?>
						<input name="name" class="namebox" value="<?php echo $rows['username']?>" readonly>
				<?php }?>
    		<?php } else {?>
					<input name="name" class="namebox" value="<?php echo $_SESSION['username']?>" readonly>
    		<?php }?>
    	<h3>内容</h3>
    		<textarea name="message" class="textbox" required><?php echo $row['message']?></textarea><br>

    		<p>画像：<?php if($row['image']) { echo $row['image']; } else { echo "無し"; } ?></p>
    		変更する画像を選んでください：
    		<input type="file" name="image" accept="image/*"><br><br>
		<?php endforeach;?>
    	<button class="button1" type="submit">編集 / 編集・画像変更・画像追加</button>　
    	<?php if($row['image']) { ?>
    	<button name="delete" class="button1" type="submit" value="delete">編集・画像削除</button> 
    	<?php } ?>
	</form>
	<br>
	<button class="button1" onclick="location.href='users.php'">キャンセル</button>
</section>