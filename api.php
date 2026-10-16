<?php
// Player API. The outcome is decided HERE with random_int(); the browser only receives it after picking.
require __DIR__.'/config.php';
header('Content-Type: application/json');header('Cache-Control: no-store');
function out($d,$c=200){http_response_code($c);echo json_encode($d);exit;}
try{
$a=$_GET['a']??'';$db=db();$post=$_SERVER['REQUEST_METHOD']==='POST';
if($a==='prizes'){out(['prizes'=>$db->query("SELECT name,`value`,image,description FROM prizes WHERE status=1")->fetchAll()]);}
if($a==='start'&&$post){
 $n=max(3,min(6,(int)($db->query("SELECT v FROM settings WHERE k='boxes'")->fetchColumn()?:3)));
 $roll=random_int(0,9999);$pid=0;$c=0;
 foreach($db->query("SELECT id,odds FROM prizes WHERE status=1 AND odds>0 ORDER BY id")->fetchAll() as $p){$c+=(int)$p['odds'];if($roll<$c){$pid=(int)$p['id'];break;}}
 $wb=$pid?random_int(0,$n-1):-1;
 $seed=bin2hex(random_bytes(32));$gid=strtoupper(bin2hex(random_bytes(12)));
 $commit=hash('sha256',"$seed|$roll|$pid|$wb");
 $db->prepare("INSERT INTO game_sessions(game_id,seed,commit_hash,boxes,roll,prize_id,win_box) VALUES(?,?,?,?,?,?,?)")->execute([$gid,$seed,$commit,$n,$roll,$pid?:null,$pid?$wb:null]);
 out(['game_id'=>$gid,'commit'=>$commit,'boxes'=>$n]);
}
if($a==='pick'&&$post){
 $in=json_decode(file_get_contents('php://input'),true);$gid=$in['game_id']??'';$b=$in['box']??null;
 if(!is_string($gid)||!preg_match('/^[A-F0-9]{24}$/',$gid)||!is_int($b))out(['error'=>'Invalid request.'],400);
 $db->beginTransaction();
 $s=$db->prepare("SELECT * FROM game_sessions WHERE game_id=? FOR UPDATE");$s->execute([$gid]);$g=$s->fetch();
 if(!$g){$db->rollBack();out(['error'=>'Game session not found. Please start a new round.'],404);}
 if($g['status']!=='open'){$db->rollBack();out(['error'=>'This round is already finished.'],409);}
 if($b<0||$b>=(int)$g['boxes']){$db->rollBack();out(['error'=>'Invalid box.'],400);}
 $prize=null;
 if($g['prize_id']&&$b===(int)$g['win_box']){$q=$db->prepare("SELECT name,`value`,image FROM prizes WHERE id=?");$q->execute([$g['prize_id']]);$prize=$q->fetch()?:null;}
 $win=$prize!==null;
 $db->prepare("UPDATE game_sessions SET status='done' WHERE game_id=?")->execute([$gid]);
 $db->prepare("INSERT INTO game_results(game_id,selected_box,result,prize_id) VALUES(?,?,?,?)")->execute([$gid,$b,$win?'win':'lose',$win?$g['prize_id']:null]);
 $db->commit();
 $wb=$g['win_box']===null?-1:(int)$g['win_box'];
 out(['win'=>$win,'prize'=>$prize,'payload'=>"{$g['seed']}|{$g['roll']}|".(int)$g['prize_id']."|$wb"]);
}
out(['error'=>'Invalid API request.'],400);
}catch(Throwable $e){error_log($e->getMessage());if(isset($db)&&$db->inTransaction())$db->rollBack();out(['error'=>'Server error. Please try again.'],500);}
