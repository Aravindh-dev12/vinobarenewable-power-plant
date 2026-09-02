<?php
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors','1');
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/plant_config.php';

class PlantWSClient {
    private $socket=null; private string $host; private int $port; private string $path;
    public function __construct(string $host,int $port,string $path='/'){ $this->host=$host;$this->port=$port;$this->path=$path; }
    public function connect(): bool {
        $this->socket=@fsockopen($this->host,$this->port,$errno,$errstr,10);if(!$this->socket){echo "[WS] $errstr ($errno)\n";return false;}
        stream_set_timeout($this->socket,60);stream_set_blocking($this->socket,false);$key=base64_encode(random_bytes(16));
        $h="GET {$this->path} HTTP/1.1\r\nHost: {$this->host}:{$this->port}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\n\r\n";fwrite($this->socket,$h);
        $resp='';$start=time();while(time()-$start<5){$line=fgets($this->socket);if($line===false){usleep(10000);continue;}$resp.=$line;if($line==="\r\n")break;}
        return strpos($resp,'101')!==false;
    }
    public function send(string $payload): void {
        if(!$this->socket)return;$len=strlen($payload);$frame=chr(0x81);$mask=random_bytes(4);
        if($len<=125)$frame.=chr(0x80|$len);elseif($len<=65535)$frame.=chr(0x80|126).pack('n',$len);else $frame.=chr(0x80|127).pack('NN',0,$len);
        $frame.=$mask;for($i=0;$i<$len;$i++)$frame.=$payload[$i]^$mask[$i%4];@fwrite($this->socket,$frame);
    }
    private function exact(int $n): ?string {$b='';while(strlen($b)<$n){$c=@fread($this->socket,$n-strlen($b));if($c===false||$c===''){if(feof($this->socket))return null;usleep(1000);continue;}$b.=$c;}return $b;}
    public function read(): ?array {
        $h=$this->exact(2);if($h===null||strlen($h)<2)return null;$b1=ord($h[0]);$b2=ord($h[1]);$op=$b1&0x0f;$masked=($b2>>7)&1;$len=$b2&0x7f;
        if($len===126){$e=$this->exact(2);if($e===null)return null;$len=unpack('n',$e)[1];}elseif($len===127){$e=$this->exact(8);if($e===null)return null;$u=unpack('N2',$e);$len=($u[1]<<32)|$u[2];}
        $mask=$masked?$this->exact(4):null;$payload=$len?$this->exact($len):'';if($payload===null)return null;if($mask){for($i=0;$i<$len;$i++)$payload[$i]=$payload[$i]^$mask[$i%4];}
        if($op===0x08)return ['op'=>'close','payload'=>''];if($op===0x09){$this->pong($payload);return ['op'=>'ping','payload'=>''];}return ['op'=>$op===0x01?'text':'other','payload'=>$payload];
    }
    private function pong(string $payload): void {$len=strlen($payload);$mask=random_bytes(4);$f=chr(0x8A).chr(0x80|$len).$mask;for($i=0;$i<$len;$i++)$f.=$payload[$i]^$mask[$i%4];@fwrite($this->socket,$f);}
    public function close(): void {if($this->socket){@fclose($this->socket);$this->socket=null;}}
}

function valueByRegex(array $values,string $regex,float $default=0): float {foreach($values as $k=>$v)if(preg_match($regex,strtolower((string)$k)))return (float)$v;return $default;}
function inverterPower(array $v): float {foreach($v as $k=>$x){$l=strtolower((string)$k);if(preg_match('/active.*power|ac.*power|power.*ac|a\.c\..*power/',$l)&&!preg_match('/reactive|apparent|3.phase/',$l))return (float)$x;}return 0;}
function inverterStrings(array $v): array {$a=0;$t=0;$strings=[];foreach($v as $k=>$x){$l=strtolower((string)$k);if(!preg_match('/\d/',$k)||!preg_match('/curr|current|amp/i',$k))continue;if(preg_match('/phase|3.phase|reactive|apparent|freq|temp|inverter.*curr|total.*curr|grid.*curr|load.*curr|mppt.*curr|dc.*curr/',$l))continue;$n=preg_match('/(\d+)/',$k,$m)?(int)$m[1]:$t+1;$cur=(float)$x;$strings[]=['n'=>$n,'current'=>$cur,'active'=>$cur>.5?1:0];$t++;if($cur>.5)$a++;}return [$a,$t,$strings];}
function insertInverter(mysqli $conn,string $unit,array $d): void {
    $v=$d['values']??[];if(!$v)return;$dev=(string)($d['device']??'Inverter');$pwr=inverterPower($v);$daily=valueByRegex($v,'/daily.*generation|daily.*gen/');$total=valueByRegex($v,'/total.*generation/');$temp=valueByRegex($v,'/internal.*temp|internal.*ambient/');[$active,$count,$strings]=inverterStrings($v);$status=$pwr>.01?'online':'offline';
    $st=$conn->prepare('INSERT INTO inverter_readings (plant_id,device_name,ac_active_power,internal_temp,daily_generation,total_generation,active_strings,total_strings,status) VALUES (?,?,?,?,?,?,?,?,?)');if($st){$st->bind_param('ssddddiis',$unit,$dev,$pwr,$temp,$daily,$total,$active,$count,$status);$st->execute();$st->close();}
    foreach($strings as $s){$volt=0.0;$ss=$conn->prepare('INSERT INTO inverter_strings (plant_id,inverter_name,string_number,current,voltage,is_active) VALUES (?,?,?,?,?,?)');if($ss){$n=$s['n'];$cur=$s['current'];$act=$s['active'];$ss->bind_param('ssiddd',$unit,$dev,$n,$cur,$volt,$act);$ss->execute();$ss->close();}}
}
function insertVcb(mysqli $conn,string $unit,array $d): void {$v=$d['values']??[];if(!$v)return;$power=(float)($v['3 Phase Active Power']??0);$export=(float)($v['Active Total Export']??0);$tag=$d['virtualTags']['vcb-today']??null;$today=(float)(is_array($tag)?($tag['value']??0):($tag??0));$st=$conn->prepare('INSERT INTO vcb_readings (plant_id,active_power_total,active_total_export,today_energy) VALUES (?,?,?,?)');if($st){$st->bind_param('sddd',$unit,$power,$export,$today);$st->execute();$st->close();}}
function insertTransformer(mysqli $conn,string $unit,array $d): void {$v=$d['values']??[];if(!$v)return;$dev=(string)($d['device']??'Transformer');$oil=array_key_exists('oil-temp',$v)?(float)$v['oil-temp']:null;$wind=array_key_exists('winding-temp',$v)?(float)$v['winding-temp']:null;$status=(($oil!==null&&$oil>80)||($wind!==null&&$wind>100))?'warning':'normal';$st=$conn->prepare('INSERT INTO transformer_readings (plant_id,device_name,oil_temp,winding_temp,status) VALUES (?,?,?,?,?)');if($st){$st->bind_param('ssdds',$unit,$dev,$oil,$wind,$status);$st->execute();$st->close();}}
function insertWeather(mysqli $conn,string $unit,array $d): void {$v=$d['values']??[];$rad=(float)($v['raw data']??0);$temp=(float)($v['pannel temperature']??0);$wind=(float)($v['windspeed']??0);$st=$conn->prepare('INSERT INTO weather_readings (plant_id,radiation,panel_temp,wind_speed) VALUES (?,?,?,?)');if($st){$st->bind_param('sddd',$unit,$rad,$temp,$wind);$st->execute();$st->close();}}
$lastTelemetry=[];function telemetry(mysqli $conn,string $unit,string $type,float $value): void {global $lastTelemetry;$key="$unit|$type";$now=time();if(isset($lastTelemetry[$key])&&$now-$lastTelemetry[$key]<3600)return;$lastTelemetry[$key]=$now;$st=$conn->prepare('INSERT INTO telemetry_history (plant_id,metric_type,metric_value) VALUES (?,?,?)');if($st){$st->bind_param('ssd',$unit,$type,$value);$st->execute();$st->close();}}

$plants=array_keys(plant_catalog());$allowed=array_fill_keys($plants,true);$runSeconds=(int)(getenv('RUN_SECONDS')?:($_GET['run_seconds']??0));$started=time();
while(true){if($runSeconds>0&&time()-$started>=$runSeconds)exit(0);$ws=new PlantWSClient('161.97.87.75',5000,'/');if(!$ws->connect()){sleep(5);continue;}foreach($plants as $p){$ws->send(json_encode(['type'=>'subscribe','unit_id'=>$p]));echo "[WS] subscribed $p\n";}
    while(true){if($runSeconds>0&&time()-$started>=$runSeconds){$ws->close();exit(0);}$frame=$ws->read();if($frame===null||$frame['op']==='close')break;if($frame['op']!=='text'||$frame['payload']==='')continue;$d=json_decode($frame['payload'],true);if(!$d||!isset($d['unit_id'])||!isset($allowed[$d['unit_id']]))continue;$unit=$d['unit_id'];$task=strtolower((string)($d['task']??''));$dev=strtolower((string)($d['device']??''));$v=$d['values']??[];
        $vcbKeys=isset($v['3 Phase Active Power'])&&(isset($v['R Phase-N Voltage'])||isset($v['Active Total Export']));if($task==='vcb'||str_contains($dev,'vcb')||$vcbKeys){insertVcb($conn,$unit,$d);if(isset($v['3 Phase Active Power']))telemetry($conn,$unit,'vcb_power',(float)$v['3 Phase Active Power']);continue;}
        if($task==='transformer'||str_contains($dev,'transformer')){insertTransformer($conn,$unit,$d);continue;}
        if(isset($v['raw data'])||isset($v['pannel temperature'])||isset($v['windspeed'])){insertWeather($conn,$unit,$d);if(isset($v['raw data']))telemetry($conn,$unit,'radiation',(float)$v['raw data']);continue;}
        $keys=array_keys($v);$isInv=$task==='inverter'||str_contains($dev,'inverter');if(!$isInv){foreach($keys as $k){$l=strtolower($k);if(preg_match('/active.*power|ac.*power/',$l)&&!preg_match('/reactive|apparent|3.phase/',$l)){$isInv=true;break;}}}
        if($isInv){insertInverter($conn,$unit,$d);$p=inverterPower($v);if($p>0)telemetry($conn,$unit,'inverter_power',$p);}
    }$ws->close();sleep(5);
}
