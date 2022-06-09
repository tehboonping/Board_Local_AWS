<?php
session_start();

if(!$_SESSION['name'])
{
	echo "アクセス拒否！IDもしくは名前が存在しません。";
	return;
}

require 'aws/aws-autoloader.php';
use Aws\S3\S3Client;  
use Aws\S3\Exception\S3Exception;

$bucket = 'webboarddatas';
$imagepath = "https://webboarddatas.s3.ap-northeast-1.amazonaws.com/";

$host = "boarddata.cchpc7kznfed.ap-northeast-1.rds.amazonaws.com";
$user = "root";
$password = "password";
$database = "boarddata";

$mysqli = new mysqli($host, $user, $password, $database);
if($mysqli->connect_errno)
{
	echo "DB接続失敗". $mysqli->connect_error;
}	

$name = $_SESSION['name'];

date_default_timezone_set("Asia/Tokyo");
$today = date("Y-m-d");
$now = date("Y-m-d H:i:s");

if($_POST['function']){
	$function = $_POST["function"]; 
}
else if($_SESSION["function"]) {
	$function = $_SESSION["function"]; 
}
else {
	$function = "none";
}

if($_POST['managerid'] && $_POST['pass'])
{
	$createid = $_POST["managerid"];
	$pass = $_POST['pass'];
	$security = $createid.$pass;
	$passsave = password_hash($security, PASSWORD_DEFAULT);

	$data = $mysqli->query("SELECT * FROM managers WHERE manager = '$createid'");
	if(!$data) { echo "dataが存在しません"; }

	$row = mysqli_fetch_array($data, MYSQLI_ASSOC);

	if($createid === $row['manager'])
	{
		$_SESSION['msg'] = '既存の管理者IDが存在します。別のIDを入力してください。';
		$_SESSION['color'] = 'red';
	}
	else
	{
		$db = $mysqli->query("INSERT INTO managers(manager,pass,name) VALUES('$createid','$passsave','DWE_$createid')");
		$_SESSION['msg'] ='管理者作成完了！';
		$_SESSION['color'] = 'green';

		if(!$db) { echo "保存失敗"; }

		$createid = "";
		$pass = "";
		$_POST['managerid'] = NULL;
		$_POST['pass'] = NULL;

		header('Location: managers.php');
		exit();
	}
}
else if($_POST['editid'])
{
	$_SESSION['scrolly'] = $_POST['pagey'];
	$id = $_POST['editid'];
	$message = $_POST['messages'];
	$edit = $mysqli->query("UPDATE datas SET message='$message' WHERE id = '$id'");

	$_POST['editid'] = NULL;
	$_POST['messages'] = NULL;
	header('Location: managers.php');
	exit();
}
else if($_POST['deleteid'])
{
	$id = $_POST['deleteid'];

	$image = $mysqli->query("SELECT * FROM datas WHERE id=$id AND image IS NOT NULL");
	if($image)
	{
		foreach($image as $row)
		{
			$filename = $row['image'];

			if($filename && $filename <> "")
			{
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
		}
	}

	$delete = $mysqli->query("DELETE FROM datas WHERE id = '$id'");

	$_POST['deleteid'] = NULL;
	header('Location: managers.php');
	exit();
}
else if($_POST['showimages'])
{
	$exist = false;
	$id = $_POST['showimages'];
	$_SESSION['scrolly'] = $_POST['pagey'];

	$_SESSION['array'][0] = array('id' => 0,'show' => false);

	foreach($_SESSION['array'] as $arr)
	{
		if($arr['id'] == $id) { $exist = true; }
	}

	if(!$exist)
	{
		$_SESSION['array'][] = array('id' => $id,'show' => true);

		$_GET['showimages'] = NULL;
	}
}
else if($_POST['imagedelete'])
{
	$id = $_POST['imagedelete'];

	$image = $mysqli->query("SELECT * FROM datas WHERE id=$id");
	foreach($image as $row)
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

	$deleteimage = $mysqli->query("UPDATE datas SET image=NULL WHERE id = '$id'");

	$_POST['imagedelete'] = NULL;
	header('Location: managers.php');
	exit();
}
else if($_POST['starttime'] && $_POST['endtime'])
{
	$startdate = $_POST['startdate'];
	$enddate = $_POST['enddate'];
	$starttime = $_POST['starttime'];
	$endtime = $_POST['endtime'];
	$text = $_POST['message'];
	$enable = $_POST['enable'];

	if(!$startdate) { $startdate = $today; }
	if(!$enddate) { $enddate = $today; }
	if(!$text) { $text = "大変申し訳ございません。只今メンテナンス中でございます。"; }

	$start = $startdate." ".$starttime;
	$end = $enddate." ".$endtime;

	if($start <= $now || $end <= $now)
	{
		$_SESSION['msg'] ='過去の期間は設定できません。';
		$_SESSION['color'] = 'red';
	}
	else
	{
		$maintenance = $mysqli->query("INSERT INTO maintenances(starttime,endtime,comment,enable,name) VALUES('$start','$end','$text',$enable,'$name')");

		$_SESSION['msg'] ='メンテナンス期間設定完了！';
		$_SESSION['color'] = 'green';

		if(!$maintenance) { echo "メンテナンス保存失敗"; }

		header('Location: managers.php');
		exit();
	}
}
else if($_POST['change'])
{
	$change = $_POST['change'];
	$changeid = $_POST['showid'];
	$maintenance = $mysqli->query("UPDATE maintenances SET enable=$change WHERE id='$changeid'");

	header('Location: managers.php');
	exit();
}
else if($_SERVER['REQUEST_METHOD'] === 'POST')
{
	$_SESSION['scrolly'] = NULL;
	$_SESSION['array'] = NULL;
	$_SESSION['msg'] = NULL;
	$_SESSION['color'] = NULL;
	$_SESSION['function'] = $function;

	header('Location: managers.php');
	exit();
}

$data = $mysqli->query("SELECT * FROM datas order by posttime desc");
if(!$data)
{
	echo "データテーブルが存在しない。";
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
	<title>掲示板管理</title>
	<link href="css/style.css" rel="stylesheet">
</head>
<body bgcolor="black" text="white">
	<button onclick="location.href='index.php'" class="return">←ログイン画面に戻る</button>
	<h1 class="title">掲示板管理</h1>
		<div class="parent">
			<div class="funtions"><h2>機能一覧</h2></div>
			<div class="namebox"><h2><?php echo $name; ?></h2></div>
		</div>
		<div class="parent">
			<div class="columns">
				<form action="managers.php" method="post">
					<button class="chooses" type="submit" name="function" value="edit">編集</button>
					<button class="chooses" type="submit" name="function" value="delete">削除</button>
					<button class="chooses" type="submit" name="function" value="images">画像一覧</button>
					<button class="chooses" type="submit" name="function" value="imagesdelete">画像削除</button>
					<button class="chooses" type="submit" name="function" value="maintenance">メンテナンス設定</button>
					<button class="chooses" type="submit" name="function" value="showmaintenance">メンテナンス期間一覧</button>
					<button class="createacc" type="submit" name="function" value="managercreate">管理者作成</button>
				</form>
			<input id="pagesave" value="<?php echo $_SESSION['scrolly'] ?>" hidden>
			</div>
			<div class="pages" id="pages">
			<?php switch($function) { 
				case "managercreate": ?>
				<center><h3>掲示板管理者作成</h3><br>
				<form action="managers.php" method="post">
					<h4>管理者ID</h4>
					<input name="managerid" class="managerids" value="<?php echo $createid; ?>" required>

					<h4>パスワード</h4>
					<input name="pass" type="password" class="passwords" value="<?php echo $pass; ?>" required>
					<br><br><button class="button2" type="submit">作成</button>
					<br><p style="color: <?php echo $_SESSION['color']; ?>"><?php echo $_SESSION['msg']; ?></p>
				</form></center>
				<?php break;

				case "edit": 
				case "delete": ?>
				<?php if($function == "edit") { ?>
					<h2 align="center" style="color:lime"><?php echo"投稿内容 編集"; }

				else { ?>
					<h2 align="center" style="color:red"><?php echo"投稿内容 削除"; } ?></h2>

				<?php foreach($data as $row): ?>
				<div class="comment">
					<p class="commentname">投稿ID：<?php echo $row['id']; ?></p>
					<?php if(!$row['accountid']) {?>
						<p class="commentname">名前 : <?php echo $row['name']; ?></p>

					<?php } else {
							$commentcaaid = $row['accountid'];
							$account = $mysqli->query("SELECT * FROM accounts WHERE accountid = '$commentcaaid'");
							$rows = mysqli_fetch_array($account, MYSQLI_ASSOC); ?>
							<p class="commentname">名前 : <?php echo $rows['username']; ?></p>
					<?php }?>
					<p class="commenttime">時刻 : <?php echo $row['posttime']; ?></p>
					<p class="commentname">画像：<?php if($row['image']) { echo $row['image']; } else { echo "無し"; } ?></p>

					<?php if($function == "edit") { ?>
					<form action="managers.php" method="post">
						<p class="info">投稿内容 : </p><textarea class="textbox" name="messages"><?php echo $row['message']; ?></textarea>
						<input id="pageload" name="pagey" class="save" hidden>
						<div class="buttons"><button onclick="setScrollPositionsInHiddenFields()" type="submit" class="button2" name="editid" value="<?php echo $row['id']; ?>">編集</button></div>
					</form>
					<?php } ?>
					<?php if($function == "delete") { ?>
					<p class="info">投稿内容 : </p><textarea class="textbox" readonly><?php echo $row['message']; ?></textarea>
					<form action="managers.php" method="post">
						<div class="buttons"><button type="submit" class="button2" name="deleteid" value="<?php echo $row['id']; ?>">削除</button></div>
					</form>
					<?php } ?>

				</div>
				<?php endforeach;
				break;

				case "images":
				case "imagesdelete": ?>
				<h2 align="center"><?php if($function == "images") { echo"画像一覧"; } else { echo"画像削除"; } ?></h2>

				<?php foreach($data as $row): 
					if($row['image']) { ?>
					<div class="comment">
					<p class="commentname">画像ID：<?php echo $row['id']; ?></p>
					<?php if(!$row['accountid']) {?>
						<p class="commentname">名前 : <?php echo $row['name']; ?></p>

					<?php } else {
							$commentcaaid = $row['accountid'];
							$account = $mysqli->query("SELECT * FROM accounts WHERE accountid = '$commentcaaid'");
							$rows = mysqli_fetch_array($account, MYSQLI_ASSOC); ?>
							<p class="commentname">名前 : <?php echo $rows['username']; ?></p>
					<?php } ?>
					<p class="commenttime">時刻 : <?php echo $row['posttime']; ?></p>
					<p class="commentname">画像：<?php echo $row['image']; ?></p>
					<?php if($_SESSION['array']) {
						foreach($_SESSION['array'] as $arr):
							if($arr['id'] === $row['id'] && $arr['show']) { ?>
							<img class="resize" src="<?php echo $imagepath.$row['image']; ?>">
							<?php break;
							} 
						endforeach; 
					} ?>
					<form action="managers.php" method="post">
						<div class="buttons">
							<input id="pageload" name="pagey" class="save" hidden>
							<button id="button" onclick="setScrollPositionsInHiddenFields()" type="submit" class="button2" name="showimages" value="<?php echo $row['id']; ?>">画像表示</button>
						</div>
					</form>

					<?php if($function == "imagesdelete") { ?>
					<form action="managers.php" method="post">
						<div class="buttons"><button type="submit" class="button2" name="imagedelete" value="<?php echo $row['id']; ?>">画像削除</button></div>
					</form>
					<?php } ?>
					</div>
				<?php }
				endforeach;
				break;

				case "maintenance": ?>
					<center><h2>メンテナンス期間の設定</h2>
					<form action="managers.php" method="post">
					<h4 class="inputtitle">開始時刻</h4>
					<input name="startdate" type="date" class="timeinput" value="<?php echo $startdate; ?>">
					<input name="starttime" type="time" class="timeinput" value="<?php echo $starttime; ?>" required>

					<h4 class="inputtitle">終了時刻</h4>
					<input name="enddate" type="date" class="timeinput" value="<?php echo $enddate; ?>">
					<input name="endtime" type="time" class="timeinput" value="<?php echo $endtime; ?>" required>

					<h4 class="inputtitle">メッセージ</h4>
					<textarea name="message" class="errormessage"><?php echo $text; ?></textarea>

					<h4 class="inputtitle">実行の許可</h4>
					<select name="enable">
						<option value="true">許可</option>
						<option value="false">拒否</option>
					</select>

					<br><br><button class="button2" type="submit">設定</button>
					<br><p style="color: <?php echo $_SESSION['color']; ?>"><?php echo $_SESSION['msg']; ?></p>
					</form></center>
				<?php break;

				case "showmaintenance": ?>
					<center><h2>メンテナンス期間一覧</h2>
					<h3>実行中</h3>
					<?php $running = $mysqli->query("SELECT * FROM maintenances WHERE starttime <= '$now' AND endtime >= '$now'");
					$exists = mysqli_fetch_array($running, MYSQLI_ASSOC);
					if(!$exists) { echo "なし"; } ?></center>
					<?php foreach($running as $row):?>
					<div class="mshow">
						ID：<?php echo $row['id']; ?>　　
						開始時刻：<?php echo $row['starttime']; ?>　　
						終了時刻：<?php echo $row['endtime']; ?>　　
						実行：
						<form action="managers.php" method="post" style="display: inline">
						<select name="change">
							<option value="<?php echo $row['enable'];?>" hidden><?php if($row['enable']) { echo "許可"; } else { echo "拒否"; } ?></option>
							<option value="true">許可</option>
							<option value="false">拒否</option>
						</select>　　
						管理者：<?php echo $row['name']; ?><br><br>
						メッセージ：<?php echo $row['comment']; ?>
						<div class="buttons"><button class="button2" type="submit" name="showid" value="<?php echo $row['id']; ?>">変更</button></div>
						</form>
					</div>
					<?php endforeach;?>

					<center><h3>実行予定</h3>
					<?php $new = $mysqli->query("SELECT * FROM maintenances WHERE starttime > '$now' ORDER BY starttime");
					$exists = mysqli_fetch_array($new, MYSQLI_ASSOC);
					if(!$exists) { echo "なし"; } ?></center>
					<?php foreach($new as $row):?>
					<div class="mshow">
						ID：<?php echo $row['id']; ?>　　
						開始時刻：<?php echo $row['starttime']; ?>　　
						終了時刻：<?php echo $row['endtime']; ?>　　
						実行：
						<form action="managers.php" method="post" style="display: inline">
						<select name="change">
							<option value="<?php echo $row['enable'];?>" hidden><?php if($row['enable']) { echo "許可"; } else { echo "拒否"; } ?></option>
							<option value="true">許可</option>
							<option value="false">拒否</option>
						</select>　　
						管理者：<?php echo $row['name']; ?><br><br>
						メッセージ：<?php echo $row['comment']; ?>
						<div class="buttons"><button class="button2" type="submit" name="showid" value="<?php echo $row['id']; ?>">変更</button></div>
						</form>
					</div>
					<?php endforeach;?>

					<center><h3>実行履歴</h3>
					<?php $old = $mysqli->query("SELECT * FROM maintenances WHERE endtime < '$now' ORDER BY starttime DESC");
					$exists = mysqli_fetch_array($old, MYSQLI_ASSOC);
					if(!$exists) { echo "なし"; } ?></center>
					<?php foreach($old as $row):?>
					<div class="mshow">
						ID：<?php echo $row['id']; ?>　　
						開始時刻：<?php echo $row['starttime']; ?>　　
						終了時刻：<?php echo $row['endtime']; ?>　　
						実行：<?php if($row['enable']) { echo "許可"; } else { echo "拒否"; } ?>　　
						管理者：<?php echo $row['name']; ?><br><br>
						メッセージ：<?php echo $row['comment']; ?>
					</div>
					<?php endforeach;?>
				<?php break;

				default: ?>
					<center>
					<br><h3>管理者専用ページ</h3>
					</center>
			<?php } ?>

			</div>
		</div>
</body>
</html>

<script>
    window.onload = function () {
        var position = document.getElementById("pagesave");
        document.getElementById("pages").scrollTo(0,position.value);
    }

	function setScrollPositionsInHiddenFields() {
        var page_y = document.getElementById("pages").scrollTop;

        const pagesave = document.getElementsByClassName("save");
        for (i = 0; i < pagesave.length; i++) {
      		pagesave[i].value = page_y;
    	}	
    }
</script>