<?php
require __DIR__.'/config.php';
$db=db();
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function tok(){return $_SESSION['t']??($_SESSION['t']=bin2hex(random_bytes(16)));}
function chk(){if(!hash_equals(tok(),(string)($_POST['t']??''))){http_response_code(403);exit('Session expired. Go back and reload the page.');}}
function csvq($v){$v=(string)$v;if(preg_match('/^[=+\-@]/',$v))$v="'".$v;return '"'.str_replace('"','""',$v).'"';}
$msg='';$err='';
$has=(int)$db->query("SELECT COUNT(*) FROM admins")->fetchColumn();
if(isset($_GET['logout'])){$_SESSION=[];session_destroy();header('Location: admin.php');exit;}
if($_SERVER['REQUEST_METHOD']==='POST'&&empty($_SESSION['aid'])){
 chk();$u=trim($_POST['u']??'');$p=(string)($_POST['p']??'');
 if(!$has){
  if(!preg_match('/^[A-Za-z0-9_.-]{3,50}$/',$u)||strlen($p)<10)$err='Username: 3-50 letters/numbers. Password: at least 10 characters.';
  else{$db->prepare("INSERT INTO admins(username,pass_hash) VALUES(?,?)")->execute([$u,password_hash($p,PASSWORD_DEFAULT)]);$has=1;$msg='Admin created. Please log in.';}
 }else{
  $s=$db->prepare("SELECT * FROM admins WHERE username=?");$s->execute([$u]);$a=$s->fetch();
  if($a&&password_verify($p,$a['pass_hash'])){session_regenerate_id(true);$_SESSION['aid']=$a['id'];header('Location: admin.php');exit;}
  sleep(1);$err='Invalid username or password.';
 }
}
$css='<style>:root{--bg:#0b0d14;--card:#171c2b;--line:#262d42;--tx:#eef0f6;--mu:#98a0b8;--gold:#f5b942;--ok:#3ddc97;--bad:#ff6b7a}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--tx);font:15px/1.5 system-ui,sans-serif}a{color:inherit;text-decoration:none}input,select,textarea{background:#0b0d14;border:1px solid var(--line);color:var(--tx);border-radius:10px;padding:10px;font:inherit;width:100%;min-height:42px}button,.b{background:linear-gradient(#ffdd8a,var(--gold));color:#1a1204;border:0;border-radius:99px;padding:10px 18px;font:700 14px inherit;cursor:pointer;min-height:42px;display:inline-block}.b2{background:none;color:var(--tx);border:1px solid var(--line)}.bd{background:#3a1820;color:var(--bad)}:focus-visible{outline:2px solid var(--gold)}.top{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid var(--line);flex-wrap:wrap;gap:8px}.nav{display:flex;gap:6px;flex-wrap:wrap}.nav a{padding:8px 14px;border-radius:99px;color:var(--mu);border:1px solid var(--line)}.nav a.on{background:var(--gold);color:#1a1204;border-color:var(--gold)}.w{max-width:1000px;margin:20px auto;padding:0 16px}.c{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:16px;margin-bottom:16px}.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}.g b{display:block;font-size:1.7rem;color:var(--gold)}.tw{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:560px}td,th{padding:8px;border-bottom:1px solid var(--line);text-align:left;vertical-align:middle}th{color:var(--mu)}.m{padding:10px 14px;border-radius:10px;margin-bottom:14px}.e{background:#3a1820;color:var(--bad)}.o{background:#12352a;color:var(--ok)}.bar{display:flex;align-items:flex-end;gap:6px;height:120px}.bar i{flex:1;background:var(--gold);border-radius:4px 4px 0 0;min-height:3px}.th{width:48px;height:48px;object-fit:cover;border-radius:8px}.f{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;align-items:end}</style>';
$head='<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>LuckBox Admin</title>'.$css.'</head><body>';
if(empty($_SESSION['aid'])){
 echo $head.'<div class="w" style="max-width:400px"><div class="c"><h2>'.($has?'Admin Login':'Create first admin').'</h2>'.($err?'<div class="m e">'.h($err).'</div>':'').($msg?'<div class="m o">'.h($msg).'</div>':'').'<form method="post"><input type="hidden" name="t" value="'.tok().'"><p><label>Username<input name="u" required autocomplete="username"></label></p><p><label>Password<input type="password" name="p" required minlength="10" autocomplete="'.($has?'current-password':'new-password').'"></label></p><button>'.($has?'Login':'Create admin').'</button></form></div></div></body></html>';exit;
}
/* ---------- CSV export ---------- */
if(isset($_GET['export'])){
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="luckbox-stats-'.date('Y-m-d').'.csv"');
 echo "\xEF\xBB\xBFSection,Label,Plays,Wins,Losses\r\n";
 foreach($db->query("SELECT DATE(created_at) d,COUNT(*) n,SUM(result='win') w FROM game_results GROUP BY d ORDER BY d") as $r)echo implode(',',array_map('csvq',['Daily',$r['d'],$r['n'],$r['w'],$r['n']-$r['w']]))."\r\n";
 foreach($db->query("SELECT COALESCE(p.name,'(deleted)') nm,COUNT(*) n FROM game_results r LEFT JOIN prizes p ON p.id=r.prize_id WHERE r.result='win' GROUP BY r.prize_id,p.name") as $r)echo implode(',',array_map('csvq',['Prize',$r['nm'],'',$r['n'],'']))."\r\n";
 exit;
}
function upload($old=''){
 if(empty($_FILES['img'])||$_FILES['img']['error']===UPLOAD_ERR_NO_FILE)return $old;
 $f=$_FILES['img'];if($f['error']!==UPLOAD_ERR_OK)throw new Exception('Upload failed (code '.$f['error'].').');
 if($f['size']>2*1024*1024)throw new Exception('Image must be under 2 MB.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
 if(!$ext||!@getimagesize($f['tmp_name']))throw new Exception('Only real JPG, PNG or WebP images are allowed.');
 $n=bin2hex(random_bytes(12)).'.'.$ext;
 if(!move_uploaded_file($f['tmp_name'],__DIR__.'/uploads/'.$n))throw new Exception('Could not save file. Check uploads/ permissions.');
 if($old&&preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/',$old))@unlink(__DIR__.'/uploads/'.$old);
 return $n;
}
/* ---------- actions ---------- */
if($_SERVER['REQUEST_METHOD']==='POST'){
 chk();$do=$_POST['do']??'';
 try{
  if($do==='prize'){
   $id=(int)($_POST['id']??0);$name=trim($_POST['name']??'');$st=empty($_POST['status'])?0:1;
   if($name===''||mb_strlen($name)>80)throw new Exception('Prize name is required (max 80 chars).');
   $old='';if($id){$q=$db->prepare("SELECT image FROM prizes WHERE id=?");$q->execute([$id]);$old=$q->fetchColumn();if($old===false)throw new Exception('Prize not found.');}
   $img=upload((string)$old);
   if($id)$db->prepare("UPDATE prizes SET name=?,description=?,`value`=?,image=?,status=? WHERE id=?")->execute([$name,mb_substr(trim($_POST['description']??''),0,255),mb_substr(trim($_POST['value']??''),0,30),$img,$st,$id]);
   else $db->prepare("INSERT INTO prizes(name,description,`value`,image,status,odds) VALUES(?,?,?,?,?,0)")->execute([$name,mb_substr(trim($_POST['description']??''),0,255),mb_substr(trim($_POST['value']??''),0,30),$img,$st]);
   $msg='Prize saved.';
  }elseif($do==='del'){
   $id=(int)$_POST['id'];$q=$db->prepare("SELECT image FROM prizes WHERE id=?");$q->execute([$id]);$im=$q->fetchColumn();
   $db->prepare("DELETE FROM prizes WHERE id=?")->execute([$id]);
   if($im&&preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/',$im))@unlink(__DIR__.'/uploads/'.$im);
   $msg='Prize deleted.';
  }elseif($do==='odds'){
   $ids=$db->query("SELECT id,status,name FROM prizes")->fetchAll();$new=[];$sum=0;
   foreach($ids as $p){$v=$_POST['odds'][$p['id']]??'0';
    if(!is_numeric($v)||$v<0||$v>100)throw new Exception('Invalid odds for "'.$p['name'].'". Use a number from 0 to 100.');
    $bp=(int)round($v*100);$new[$p['id']]=$bp;if($p['status'])$sum+=$bp;}
   if($sum>10000)throw new Exception('Active prize odds total '.($sum/100).'% - the total cannot exceed 100%.');
   $u=$db->prepare("UPDATE prizes SET odds=? WHERE id=?");foreach($new as $i=>$bp)$u->execute([$bp,$i]);
   $msg='Odds saved. "No prize" chance is now '.((10000-$sum)/100).'%.';
  }elseif($do==='settings'){
   $b=(int)$_POST['boxes'];if($b<3||$b>6)throw new Exception('Boxes must be between 3 and 6.');
   $db->prepare("REPLACE INTO settings(k,v) VALUES('boxes',?)")->execute([$b]);
   if(($_POST['np']??'')!==''){if(strlen($_POST['np'])<10)throw new Exception('Password must be at least 10 characters.');$db->prepare("UPDATE admins SET pass_hash=? WHERE id=?")->execute([password_hash($_POST['np'],PASSWORD_DEFAULT),$_SESSION['aid']]);}
   $msg='Settings saved.';
  }
 }catch(Throwable $e){$err=$e instanceof PDOException?'Database error. Please try again.':$e->getMessage();if($e instanceof PDOException)error_log($e->getMessage());}
}
$pg=$_GET['p']??'dash';if(!in_array($pg,['dash','prizes','odds','hist','set']))$pg='dash';
echo $head.'<div class="top"><b>LUCK<span style="color:var(--gold)">BOX</span> Admin</b><div class="nav">';
foreach(['dash'=>'Dashboard','prizes'=>'Prizes','odds'=>'Odds','hist'=>'History','set'=>'Settings'] as $k=>$l)echo '<a href="?p='.$k.'" class="'.($k===$pg?'on':'').'">'.$l.'</a>';
echo '<a href="?export=1">Export CSV</a><a href="index.html" target="_blank">View site</a><a href="?logout=1">Logout</a></div></div><div class="w">';
if($msg)echo '<div class="m o" role="status">'.h($msg).'</div>';if($err)echo '<div class="m e" role="alert">'.h($err).'</div>';
$T='<input type="hidden" name="t" value="'.tok().'">';
if($pg==='dash'){
 $r=$db->query("SELECT COUNT(*) n,COALESCE(SUM(result='win'),0) w FROM game_results")->fetch();$n=(int)$r['n'];$w=(int)$r['w'];
 echo '<div class="c g"><div>Total plays<b>'.$n.'</b></div><div>Wins<b>'.$w.'</b></div><div>Losses<b>'.($n-$w).'</b></div><div>Win rate<b>'.($n?round($w/$n*100,1):0).'%</b></div></div>';
 $d=$db->query("SELECT DATE(created_at) d,COUNT(*) n FROM game_results WHERE created_at>=NOW()-INTERVAL 14 DAY GROUP BY d ORDER BY d")->fetchAll();$mx=max(1,...array_column($d,'n')?:[1]);
 echo '<div class="c"><h3>Plays (last 14 days)</h3>'.($d?'<div class="bar">':'<p>No plays yet.</p>');foreach($d as $x)echo '<i style="height:'.($x['n']/$mx*100).'%" title="'.h($x['d']).': '.$x['n'].'"></i>';echo $d?'</div>':'';echo '</div>';
 echo '<div class="c tw"><h3>Prize distribution</h3><table>';$any=0;
 foreach($db->query("SELECT COALESCE(p.name,'(deleted)') nm,COUNT(*) n FROM game_results r LEFT JOIN prizes p ON p.id=r.prize_id WHERE r.result='win' GROUP BY r.prize_id,p.name") as $x){$any=1;echo '<tr><td>'.h($x['nm']).'</td><td>'.$x['n'].'</td></tr>';}
 echo $any?'':'<tr><td>No wins yet.</td></tr>';echo '</table></div>';
 echo '<div class="c tw"><h3>Recent games</h3><table><tr><th>Game ID</th><th>Time</th><th>Box</th><th>Result</th><th>Prize</th></tr>';
 foreach($db->query("SELECT r.*,p.name FROM game_results r LEFT JOIN prizes p ON p.id=r.prize_id ORDER BY r.id DESC LIMIT 10") as $x)echo '<tr><td>'.h($x['game_id']).'</td><td>'.h($x['created_at']).'</td><td>'.($x['selected_box']+1).'</td><td>'.strtoupper($x['result']).'</td><td>'.h($x['name']??'-').'</td></tr>';
 echo '</table></div>';
}
if($pg==='prizes'){
 echo '<div class="c"><h3>Add prize</h3><form method="post" enctype="multipart/form-data" class="f">'.$T.'<input type="hidden" name="do" value="prize"><input type="hidden" name="id" value="0"><label>Name<input name="name" required maxlength="80"></label><label>Value<input name="value" maxlength="30" placeholder="$50"></label><label>Description<input name="description" maxlength="255"></label><label>Image (JPG/PNG/WebP, 2 MB)<input type="file" name="img" accept="image/jpeg,image/png,image/webp"></label><label><input type="checkbox" name="status" value="1" checked style="width:auto;min-height:0"> Active</label><button>Add prize</button></form><p style="color:var(--mu)">New prizes start at 0% odds. Set odds on the Odds page.</p></div>';
 foreach($db->query("SELECT * FROM prizes ORDER BY id") as $p){
  echo '<div class="c"><form method="post" enctype="multipart/form-data" class="f">'.$T.'<input type="hidden" name="do" value="prize"><input type="hidden" name="id" value="'.$p['id'].'"><img class="th" alt="'.h($p['name']).'" src="'.($p['image']?'uploads/'.h($p['image']):'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 9 9%22%3E%3Crect width=%229%22 height=%229%22 fill=%22%23f5b942%22/%3E%3C/svg%3E').'"><label>Name<input name="name" required maxlength="80" value="'.h($p['name']).'"></label><label>Value<input name="value" maxlength="30" value="'.h($p['value']).'"></label><label>Description<input name="description" maxlength="255" value="'.h($p['description']).'"></label><label>Replace image<input type="file" name="img" accept="image/jpeg,image/png,image/webp"></label><label><input type="checkbox" name="status" value="1" '.($p['status']?'checked':'').' style="width:auto;min-height:0"> Active</label><button>Save</button></form>';
  echo '<form method="post" onsubmit="return confirm(\'Delete this prize permanently?\')" style="margin-top:8px">'.$T.'<input type="hidden" name="do" value="del"><input type="hidden" name="id" value="'.$p['id'].'"><button class="bd">Delete</button></form></div>';
 }
}
if($pg==='odds'){
 $rows=$db->query("SELECT * FROM prizes ORDER BY id")->fetchAll();$sum=0;foreach($rows as $p)if($p['status'])$sum+=$p['odds'];
 echo '<div class="c tw"><h3>Odds</h3><p style="color:var(--mu)">Chance per round (%). Active prizes must total 100% or less; the remainder is "No prize" (now '.((10000-$sum)/100).'%).</p><form method="post">'.$T.'<input type="hidden" name="do" value="odds"><table><tr><th>Prize</th><th>Status</th><th>Odds %</th></tr>';
 foreach($rows as $p)echo '<tr><td>'.h($p['name']).'</td><td>'.($p['status']?'Active':'Inactive').'</td><td><input type="number" step="0.01" min="0" max="100" name="odds['.$p['id'].']" value="'.($p['odds']/100).'" style="max-width:120px"></td></tr>';
 echo '</table><p><button>Save odds</button></p></form></div>';
}
if($pg==='hist'){
 $w=[];$a=[];$rs=$_GET['r']??'';$f=$_GET['f']??'';$t=$_GET['to']??'';
 if(in_array($rs,['win','lose'])){$w[]='r.result=?';$a[]=$rs;}
 if(preg_match('/^\d{4}-\d\d-\d\d$/',$f)){$w[]='r.created_at>=?';$a[]=$f.' 00:00:00';}
 if(preg_match('/^\d{4}-\d\d-\d\d$/',$t)){$w[]='r.created_at<=?';$a[]=$t.' 23:59:59';}
 $wh=$w?' WHERE '.implode(' AND ',$w):'';$pp=25;$pn=max(1,(int)($_GET['n']??1));
 $c=$db->prepare("SELECT COUNT(*) FROM game_results r$wh");$c->execute($a);$tot=(int)$c->fetchColumn();$pages=max(1,(int)ceil($tot/$pp));$pn=min($pn,$pages);
 $q=$db->prepare("SELECT r.*,p.name FROM game_results r LEFT JOIN prizes p ON p.id=r.prize_id$wh ORDER BY r.id DESC LIMIT $pp OFFSET ".(($pn-1)*$pp));$q->execute($a);
 echo '<div class="c"><form class="f" method="get"><input type="hidden" name="p" value="hist"><label>Result<select name="r"><option value="">All</option><option value="win"'.($rs==='win'?' selected':'').'>Win</option><option value="lose"'.($rs==='lose'?' selected':'').'>Lose</option></select></label><label>From<input type="date" name="f" value="'.h($f).'"></label><label>To<input type="date" name="to" value="'.h($t).'"></label><button>Filter</button></form></div>';
 echo '<div class="c tw"><table><tr><th>Game ID</th><th>Date/time</th><th>Box</th><th>Result</th><th>Prize</th></tr>';
 foreach($q as $x)echo '<tr><td>'.h($x['game_id']).'</td><td>'.h($x['created_at']).'</td><td>'.($x['selected_box']+1).'</td><td>'.strtoupper($x['result']).'</td><td>'.h($x['name']??($x['prize_id']?'(deleted)':'-')).'</td></tr>';
 echo $tot?'':'<tr><td colspan="5">No games found.</td></tr>';echo '</table><p>'.$tot.' games · page '.$pn.' of '.$pages.' ';
 $qs=http_build_query(['p'=>'hist','r'=>$rs,'f'=>$f,'to'=>$t]);
 if($pn>1)echo '<a class="b b2" href="?'.$qs.'&n='.($pn-1).'">Prev</a> ';if($pn<$pages)echo '<a class="b b2" href="?'.$qs.'&n='.($pn+1).'">Next</a>';echo '</p></div>';
}
if($pg==='set'){
 $b=(int)($db->query("SELECT v FROM settings WHERE k='boxes'")->fetchColumn()?:3);
 echo '<div class="c" style="max-width:420px"><h3>Game settings</h3><form method="post">'.$T.'<input type="hidden" name="do" value="settings"><p><label>Mystery boxes per round<select name="boxes">';
 foreach([3,4,5,6] as $i)echo '<option'.($i==$b?' selected':'').'>'.$i.'</option>';
 echo '</select></label></p><p><label>New admin password (optional, min 10)<input type="password" name="np" minlength="10" autocomplete="new-password"></label></p><button>Save</button></form></div>';
}
echo '</div></body></html>';
