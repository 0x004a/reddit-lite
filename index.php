<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// TOOLS

date_default_timezone_set('UTC');

function time_elapsed($time)
{
	$time = time()-$time;
	$unit = [
		31449600 => 'year',
		2620800 => 'month',
		604800 => 'week',
		86400 => 'day',
		3600 => 'hour',
		60 => 'minute',
		1 => 'second'
	];
	
	foreach ( $unit as $num => $str )
	{
		if ( $time < $num ) continue;
		
		$time = floor($time/$num);
		
		return $time.' '.$str.($time > 1 ? 's':'').' ago';
	}
	
	return 'just now';
}

function url_download($url)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$data = curl_exec($ch);
	curl_close($ch);
	
	return $data;
}

function comments_list($comments)
{
	$h = '<ul>';
	foreach ( $comments['data']['children'] as $comment )
	{
		$c = $comment['data'];
		
		if ( isset($c['body_html']) )
		{
			$h .= '<li>';
			$h .= '<p><small><strong>'.$c['author'].'</strong> - '.time_elapsed($c['created_utc']).'</small></p>';
			$h .= html_entity_decode($c['body_html'], ENT_HTML5, 'UTF-8');
			$h .= '</li>';
		}
		if ( isset($c['replies']['data']['children']) )
		{
			$h .= comments_list($c['replies']);
		}
	}
	$h .= '</ul>';
	
	return $h;
}

// EDITS

//$subs = ['all'];
$subs_file = 'var/subscriptions.json';

//if ( file_exists($subs_file) )
//{
	$subs = json_decode(file_get_contents($subs_file), true);
//}

if ( ! empty($_POST['add']) )
{
	if ( preg_match(',r/([a-zA-Z0-9]+),', $_POST['add'], $matches) )
	{
		$_POST['add'] = $matches[1];
	}
	
	$new_subs = explode('+', $_POST['add']);
	
	foreach ($new_subs as $sub)
	{
		array_push($subs, strtolower($sub));
	}

	sort($subs);
	file_put_contents($subs_file, json_encode($subs));
}

if ( ! empty($_POST['delete']) )
{
	foreach ( $_POST['delete'] as $threads )
	{
		if ( ($key = array_search($threads, $subs)) !== false )
		{
			unset($subs[$key]);
		}
	}
	
	file_put_contents($subs_file, json_encode($subs));
}

// CONTENT

$threads = implode('+', $subs);
$posts = [];
$comments = [];

if ( ! empty($_GET['threads']) )
{
	if ( preg_match(',comments/([a-zA-Z0-9]+),', $_GET['threads'], $matches) )
	{
		$data = json_decode(url_download('https://www.reddit.com/comments/'.$matches[1].'/.json'), true);
		
		$threads = $data[0]['data']['children'][0]['data']['subreddit'];
		$posts = $data[0];
		$comments = $data[1];
	}
	elseif ( preg_match(',r/([a-zA-Z0-9]+),', $_GET['threads'], $matches) )
	{
		$threads = $matches[1];
	}
	else
	{
		$threads = $_GET['threads'];
	}
}

if ( ! $posts )
{
	$posts = json_decode(url_download('https://www.reddit.com/r/'.$threads.'/.json?limit=100'), true);
}

?>
<!doctype html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reddit Lite</title>

<style>
a { text-decoration: none; }
img { max-width: 6rem; }
ul { padding-left: 1rem; }
li { border-bottom: 1px solid #eee; }
</style>

</head>
<body>

<!-- NAV -->

<ul>
<li><p><a href="./">All</a></p></li>
<li><p><a href="#subscriptions">Subscriptions &darr;</a></p></li>
<li><form method="get" action=""><p><input type="text" name="threads" value="<?php print $threads; ?>"><button>Go</button></p></form></li>
</ul>

<!-- POSTS -->

<ol>
<?php foreach ($posts['data']['children'] as $post) { $p = $post['data']; ?>
	<li>
	<p>
		<small>
			<a href="?threads=<?php print $p['subreddit']; ?>"><?php print $p['subreddit']; ?></a>
			- <strong><?php print $p['author']; ?></strong>
			- <?php print $p['num_comments']; ?> comments
			- <?php print time_elapsed($p['created_utc']); ?>
		</small>
	</p>
	<p><strong><a href="?threads=<?php print $p['permalink']; ?>"><?php print $p['title']; ?></a></strong></p>
			
	<?php if ( preg_match('/jpg$/', $p['thumbnail']) ) { ?>
		<p><img src="<?php print $p['thumbnail']; ?>"></p>
	<?php } ?>
			
	<p><small><a href="<?php print $p['url']; ?>"><?php print $p['url']; ?></a></small></p>
			
	<?php if ( $comments ) print html_entity_decode($p['selftext_html'], ENT_HTML5, 'UTF-8'); ?>	
	</li>
<?php } ?>
</ol>

<!-- COMMENTS -->

<?php if ( $comments ) print comments_list($comments); ?>

<!-- SUBSCRIPTIONS -->

<form id="subscriptions" method="post" action="">
	<p><input type="text" name="add" value="<?php print $threads; ?>"><button>Add</button></p>
</form>
<form method="post" action="">
	<ul>
	<?php foreach ($subs as $sub) { ?>
		<li>
			<input type="checkbox" name="delete[]" value="<?php print $sub; ?>">
			<a href="?threads=<?php print $sub; ?>"><?php print $sub; ?></a>
		</li>
	<?php } ?>
	</ul>
	<p><button>Delete</button></p>
</form>
<p><a href="#top">Top &uarr;</a></p>

</body>
</html>
