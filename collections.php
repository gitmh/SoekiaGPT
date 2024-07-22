<?php
if(!isset($_POST["action"])) die();

$action = $_POST["action"];
function writeFileLocked($filename,$callback){
    // Umfrage-Datei temporär vor weiteren Schreibzugriffen schützen
  clearstatcache($filename);
  $fp = fopen($filename, "r+");
  if (flock($fp, LOCK_EX)) { // exklusive Sperre
    $fc = fread($fp, filesize($filename));
    $content = json_decode($fc,true);
    $callback($content);
    fseek($fp, 0); 
    ftruncate($fp, 0); // kürze Datei
    fwrite($fp, json_encode($content,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES |JSON_PRETTY_PRINT));
    fflush($fp); // leere Ausgabepuffer bevor die Sperre freigegeben wird
    flock($fp, LOCK_UN); 
  } else {
    // das dürfte nicht vorkommen
    error_log("FILE_LOCK_FAILED");
    print "Error saving file, please try again.";
    exit();
  }  
  fclose($fp);   
}
function random_code($length){
  return substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, $length);
}

if($action == "create"){
  $code = random_code(6);
  $filename = "collections/".$code.".json";
  while(file_exists($filename)){
    $code = random_code(6);
    $filename = "collections/".$code.".json";
  }
  file_put_contents($filename, "{}");

  $writeCode = function(&$content){
    global $code;
  	$content["docs"] = [];
    if(isset($_POST["docs"])) $content["docs"] = json_decode($_POST["docs"],true);
    $content["code"] = strtoupper($code);
    return true;
  };
  writeFileLocked($filename,$writeCode);
  print '{"result":"OK", "code":"'.$code.'"}';
}

if($action == "update" && isset($_POST["code"])){
  $code = strtolower($_POST["code"]);
  $filename = "collections/".$code.".json";
  if(file_exists($filename)){
    $writeCode = function(&$content){
      global $code;
      if($_POST["value"] == "NULL"){
        unset($content["docs"][$_POST["doc"]]);
      }else{
        $content["docs"][$_POST["doc"]] = $_POST["value"];        
      }
      return true;
    };
    writeFileLocked($filename,$writeCode);
    print '{"result":"OK"}';
  }
}
if($action == "get" && isset($_POST["code"])){
  $code = strtolower($_POST["code"]);
  $filename = "collections/".$code.".json";
  if(file_exists($filename)){
    $writeCode = function(&$content){
      print json_encode($content,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      return false; // no change
    };
    writeFileLocked($filename,$writeCode);    
  }else{
    print '{"result":"FAILED", "error":"NOTFOUND"}';
  }
}
