<?php
// ===== EDIT THESE =====
const DB_HOST='localhost',DB_NAME='luckbox',DB_USER='root',DB_PASS='';
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])]);
session_start();
function db():PDO{static $p;if(!$p){try{$p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}catch(Throwable $e){error_log($e->getMessage());http_response_code(500);exit('Database connection failed. Check config.php.');}}return $p;}
