<?php
set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors','1');
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/plant_config.php';

class PlantWSClient {
    private $socket=null;
    private string $host;
    private int $port;
    private string $path;

    public function __construct(string $host,int $port,string $path='/'){
        $this->host=$host;
        $this->port=$port;
        $this->path=$path;
    }

    public function connect(): bool {
        $this->socket=@fsockopen($this->host,$this->port,$errno,$errstr,10);
        if(!$this->socket){echo "[WS] $errstr ($errno)\n";return false;}
        stream_set_timeout($this->socket,60);
        stream_set_blocking($this->socket,false);
        $key=base64_encode(random_bytes(16));
        $h="GET {$this->path} HTTP/1.1\r\nHost: {$this->host}:{$this->port}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\n\r\n";
        fwrite($this->socket,$h);
        $resp='';
        $start=microtime(true);
        while(microtime(true)-$start<5){
            $line=fgets($this->socket);
            if($line===false){usleep(10000);continue;}
            $resp.=$line;
            if($line==="\r\n")break;
        }
        if(strpos($resp,'101')===false){$this->close();return false;}
        return true;
    }

    public function socket(){return $this->socket;}

    public function send(string $payload): void {
        if(!$this->socket)return;
        $len=strlen($payload);
        $frame=chr(0x81);
        $mask=random_bytes(4);
        if($len<=125)$frame.=chr(0x80|$len);
        elseif($len<=65535)$frame.=chr(0x80|126).pack('n',$len);
        else $frame.=chr(0x80|127).pack('NN',0,$len);
        $frame.=$mask;
        for($i=0;$i<$len;$i++)$frame.=$payload[$i]^$mask[$i%4];
        @fwrite($this->socket,$frame);
    }

    private function exact(int $n): ?string {
        $b='';
        $start=microtime(true);
        while(strlen($b)<$n){
            $c=@fread($this->socket,$n-strlen($b));
            if($c===false||$c===''){
                if(!$this->socket||feof($this->socket))return null;
                if(microtime(true)-$start>2)return null;
                usleep(1000);
                continue;
            }
            $b.=$c;
        }
        return $b;
    }

    public function read(): ?array {
        $h=$this->exact(2);
        if($h===null||strlen($h)<2)return null;
        $b1=ord($h[0]);$b2=ord($h[1]);$op=$b1&0x0f;$masked=($b2>>7)&1;$len=$b2&0x7f;
        if($len===126){$e=$this->exact(2);if($e===null)return null;$len=unpack('n',$e)[1];}
        elseif($len===127){$e=$this->exact(8);if($e===null)return null;$u=unpack('N2',$e);$len=($u[1]<<32)|$u[2];}
        $mask=$masked?$this->exact(4):null;
        $payload=$len?$this->exact($len):'';
        if($payload===null)return null;
        if($mask){for($i=0;$i<$len;$i++)$payload[$i]=$payload[$i]^$mask[$i%4];}
        if($op===0x08)return ['op'=>'close','payload'=>''];
        if($op===0x09){$this->pong($payload);return ['op'=>'ping','payload'=>''];}
        return ['op'=>$op===0x01?'text':'other','payload'=>$payload];
    }

    private function pong(string $payload): void {
        $len=strlen($payload);$mask=random_bytes(4);$f=chr(0x8A).chr(0x80|$len).$mask;
        for($i=0;$i<$len;$i++)$f.=$payload[$i]^$mask[$i%4];
        @fwrite($this->socket,$f);
    }

    public function close(): void {
        if($this->socket){@fclose($this->socket);$this->socket=null;}
    }
}

function valueByRegex(array $values,string $regex,float $default=0): float {
    foreach($values as $k=>$v)if(preg_match($regex,strtolower((string)$k)))return (float)$v;
    return $default;
}

function inverterPower(array $v): float {
    foreach($v as $k=>$x){
        $l=strtolower((string)$k);
        if(preg_match('/active.*power|ac.*power|power.*ac|a\.c\..*power/',$l)&&!preg_match('/reactive|apparent|3.phase/',$l))return (float)$x;
    }
    return 0;
}

function virtualTagNumber(array $d,string $preferredKey): float {
    $tags=$d['virtualTags']??[];
    if(!is_array($tags))return 0;
    if(array_key_exists($preferredKey,$tags)){
        $raw=$tags[$preferredKey];
        return (float)(is_array($raw)?($raw['value']??0):$raw);
    }
    foreach($tags as $key=>$raw){
        if(preg_match('/vcb.*today|today.*energy/i',(string)$key))return (float)(is_array($raw)?($raw['value']??0):$raw);
    }
    return 0;
}

function inverterStrings(array $v): array {
    $byNum=[];
    foreach(array_keys($v) as $key){
        if(!preg_match('/(\d+)/',(string)$key,$m))continue;
        $n=(int)$m[1];
        $byNum[$n]??=[];
        $byNum[$n][]=(string)$key;
    }

    $active=0;$total=0;$strings=[];
    foreach($byNum as $n=>$group){
        $currKey='';$voltKey='';
        foreach($group as $key){
            $l=strtolower($key);
            if(preg_match('/phase|phasa|ph_|r.phase|y.phase|b.phase|a.phase|c.phase|3.phase|three.phase/',$l))continue;
            if(preg_match('/inverter.*curr|inv.*curr|total.*curr|grid.*curr|load.*curr|reactive.*curr|mppt.*curr|dc.*curr/',$l))continue;
            if(preg_match('/freq|temperature|temp|ambient|cosphi|pf.*_/',$l))continue;
            if($currKey===''&&preg_match('/\b(curr|current|amp|i)\b/',$l)&&!preg_match('/\b(volt|voltage|temp|freq)\b/',$l))$currKey=$key;
            if($voltKey===''&&preg_match('/\b(volt|voltage|v)\b/',$l)&&!preg_match('/\b(curr|current|amp|i)\b/',$l))$voltKey=$key;
        }
        if($currKey==='')continue;
        $current=(float)($v[$currKey]??0);
        $voltage=$voltKey!==''?(float)($v[$voltKey]??0):0.0;
        $isActive=$current>0.5?1:0;
        $strings[]=['n'=>(int)$n,'current'=>$current,'voltage'=>$voltage,'active'=>$isActive];
        $total++;
        if($isActive)$active++;
    }
    usort($strings,fn(array $a,array $b)=>$a['n']<=>$b['n']);
    return [$active,$total,$strings];
}

function insertVcb(mysqli $conn,string $unit,array $d): void {
    $v=$d['values']??[];
    if(!is_array($v)||!$v)return;

    $today=virtualTagNumber($d,'vcb-today');
    $activeTotal=(float)($v['3 Phase Active Power']??0);
    $activeR=(float)($v['Active Power R']??0);
    $activeY=(float)($v['Active Power Y']??0);
    $activeB=(float)($v['Active Power B']??0);
    $frequency=(float)($v['Frequency (Hz)']??0);
    $voltageRn=(float)($v['R Phase-N Voltage']??0);
    $voltageYn=(float)($v['Y Phase-N Voltage']??0);
    $voltageBn=(float)($v['B Phase-N Voltage']??0);
    $voltageRy=(float)($v['V12 (RY)']??0);
    $voltageYb=(float)($v['V23 (YB)']??0);
    $voltageBr=(float)($v['V31 (BR)']??0);
    $currentR=(float)($v['L1 (R)']??0);
    $currentY=(float)($v['L2 (Y)']??0);
    $currentB=(float)($v['L3 (B)']??0);
    $pfQ1=(float)($v['Q1 PF']??0);
    $pfQ2=(float)($v['Q2 PF']??0);
    $pfQ3=(float)($v['Q3 PF']??0);
    $thdR=(float)($v['Voltage THD R']??0);
    $thdY=(float)($v['Voltage THD Y']??0);
    $thdB=(float)($v['Voltage THD B']??0);
    $export=(float)($v['Active Total Export']??0);
    $import=(float)($v['Active Total Import']??0);
    $reactiveImport=(float)($v['Reactive Import (Q1+Q2)']??0);
    $reactiveExport=(float)($v['Reactive Export (Q3+Q4)']??0);

    $sql='INSERT INTO vcb_readings (plant_id,active_power_total,active_power_r,active_power_y,active_power_b,frequency,voltage_rn,voltage_yn,voltage_bn,voltage_ry,voltage_yb,voltage_br,current_r,current_y,current_b,pf_q1,pf_q2,pf_q3,voltage_thd_r,voltage_thd_y,voltage_thd_b,active_total_export,active_total_import,reactive_import_q1q2,reactive_export_q3q4,today_energy) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
    $st=$conn->prepare($sql);
    if(!$st){echo "[DB ERROR] VCB prepare failed: {$conn->error}\n";return;}
    $st->bind_param(
        'sddddddddddddddddddddddddd',
        $unit,$activeTotal,$activeR,$activeY,$activeB,$frequency,
        $voltageRn,$voltageYn,$voltageBn,$voltageRy,$voltageYb,$voltageBr,
        $currentR,$currentY,$currentB,$pfQ1,$pfQ2,$pfQ3,$thdR,$thdY,$thdB,
        $export,$import,$reactiveImport,$reactiveExport,$today
    );
    if(!$st->execute())echo "[DB ERROR] VCB execute failed: {$st->error}\n";
    $st->close();
}

function insertInverter(mysqli $conn,string $unit,array $d): void {
    $v=$d['values']??[];
    if(!is_array($v)||!$v)return;

    $dev=trim((string)($d['device']??'Inverter'))?:'Inverter';
    $power=inverterPower($v);
    [$activeStrings,$totalStrings,$strings]=inverterStrings($v);
    $status=$power>0.01?'online':'offline';

    $reactive=(float)($v['a.c. reactive power']??0);
    $pf=(float)($v['Power Factor']??0);
    $voltageAb=(float)($v['a.c. voltage AB']??0);
    $voltageBc=(float)($v['a.c. voltage BC']??0);
    $voltageCa=(float)($v['a.c. voltage CA']??0);
    $frequency=(float)($v['a.c. frequency']??0);
    $currentA=(float)($v['A phase current']??0);
    $currentB=(float)($v['B phase current']??0);
    $currentC=(float)($v['C phase current']??0);
    $efficiency=(float)($v['inverter efficiency']??0);
    $internalTemp=(float)($v['internal ambient temperature']??0);
    $dailyGeneration=(float)($v['daily generation']??0);
    $totalGeneration=(float)($v['total generation']??0);
    $dailyCo2=(float)($v['daily CO2 reduction']??0);
    $totalCo2=(float)($v['total CO2 reduction']??0);
    $dailyHours=(float)($v['daily working hours']??0);
    $totalHours=(float)($v['total working hours']??0);

    $sql='INSERT INTO inverter_readings (plant_id,device_name,ac_active_power,ac_reactive_power,power_factor,ac_voltage_ab,ac_voltage_bc,ac_voltage_ca,ac_frequency,phase_current_a,phase_current_b,phase_current_c,inverter_efficiency,internal_temp,daily_generation,total_generation,daily_co2_reduction,total_co2_reduction,daily_working_hours,total_working_hours,active_strings,total_strings,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
    $st=$conn->prepare($sql);
    if(!$st){echo "[DB ERROR] Inverter prepare failed: {$conn->error}\n";return;}
    $st->bind_param(
        'ssddddddddddddddddddiis',
        $unit,$dev,$power,$reactive,$pf,$voltageAb,$voltageBc,$voltageCa,$frequency,
        $currentA,$currentB,$currentC,$efficiency,$internalTemp,$dailyGeneration,$totalGeneration,
        $dailyCo2,$totalCo2,$dailyHours,$totalHours,$activeStrings,$totalStrings,$status
    );
    if(!$st->execute())echo "[DB ERROR] Inverter execute failed: {$st->error}\n";
    $st->close();

    foreach($strings as $string){
        $ss=$conn->prepare('INSERT INTO inverter_strings (plant_id,inverter_name,string_number,current,voltage,is_active) VALUES (?,?,?,?,?,?)');
        if(!$ss){echo "[DB ERROR] String prepare failed: {$conn->error}\n";continue;}
        $number=(int)$string['n'];
        $current=(float)$string['current'];
        $voltage=(float)$string['voltage'];
        $active=(int)$string['active'];
        $ss->bind_param('ssiddi',$unit,$dev,$number,$current,$voltage,$active);
        if(!$ss->execute())echo "[DB ERROR] String execute failed: {$ss->error}\n";
        $ss->close();
    }
}

function insertTransformer(mysqli $conn,string $unit,array $d): void {
    $v=$d['values']??[];
    if(!is_array($v)||!$v)return;
    $dev=trim((string)($d['device']??'Transformer'))?:'Transformer';
    $oil=array_key_exists('oil-temp',$v)?(float)$v['oil-temp']:null;
    $wind=array_key_exists('winding-temp',$v)?(float)$v['winding-temp']:null;
    $status=(($oil!==null&&$oil>80)||($wind!==null&&$wind>100))?'warning':'normal';
    $st=$conn->prepare('INSERT INTO transformer_readings (plant_id,device_name,oil_temp,winding_temp,status) VALUES (?,?,?,?,?)');
    if(!$st){echo "[DB ERROR] Transformer prepare failed: {$conn->error}\n";return;}
    $st->bind_param('ssdds',$unit,$dev,$oil,$wind,$status);
    if(!$st->execute())echo "[DB ERROR] Transformer execute failed: {$st->error}\n";
    $st->close();
}

function insertWeather(mysqli $conn,string $unit,array $d): void {
    $v=$d['values']??[];
    if(!is_array($v)||!$v)return;
    $radiation=(float)($v['raw data']??0);
    $panelTemp=(float)($v['pannel temperature']??0);
    $windSpeed=(float)($v['windspeed']??0);
    $st=$conn->prepare('INSERT INTO weather_readings (plant_id,radiation,panel_temp,wind_speed) VALUES (?,?,?,?)');
    if(!$st){echo "[DB ERROR] Weather prepare failed: {$conn->error}\n";return;}
    $st->bind_param('sddd',$unit,$radiation,$panelTemp,$windSpeed);
    if(!$st->execute())echo "[DB ERROR] Weather execute failed: {$st->error}\n";
    $st->close();
}

$lastTelemetry=[];
function telemetry(mysqli $conn,string $unit,string $type,float $value): void {
    global $lastTelemetry;
    $key="$unit|$type";$now=time();
    if(isset($lastTelemetry[$key])&&$now-$lastTelemetry[$key]<3600)return;
    $lastTelemetry[$key]=$now;
    $st=$conn->prepare('INSERT INTO telemetry_history (plant_id,metric_type,metric_value) VALUES (?,?,?)');
    if($st){$st->bind_param('ssd',$unit,$type,$value);$st->execute();$st->close();}
}

function processPlantMessage(mysqli $conn,string $expectedPlant,array $allowed,array $d): void {
    if(!isset($d['unit_id']))return;
    $unit=normalize_plant_id((string)$d['unit_id']);
    if($unit!==$expectedPlant||!isset($allowed[$unit]))return;

    $task=strtolower((string)($d['task']??''));
    $dev=strtolower((string)($d['device']??''));
    $v=$d['values']??[];
    if(!is_array($v))$v=[];

    $isVcb=$task==='vcb'||strpos($dev,'vcb')!==false||(
        isset($v['3 Phase Active Power'])&&
        (isset($v['R Phase-N Voltage'])||isset($v['Active Total Export']))
    );
    if($isVcb){
        insertVcb($conn,$unit,$d);
        if(isset($v['3 Phase Active Power']))telemetry($conn,$unit,'vcb_power',(float)$v['3 Phase Active Power']);
        return;
    }

    $isTransformer=$task==='transformer'||strpos($dev,'transformer')!==false;
    if($isTransformer){
        insertTransformer($conn,$unit,$d);
        if(isset($v['oil-temp']))telemetry($conn,$unit,'oil_temp',(float)$v['oil-temp']);
        if(isset($v['winding-temp']))telemetry($conn,$unit,'winding_temp',(float)$v['winding-temp']);
        return;
    }

    if(isset($v['raw data'])||isset($v['pannel temperature'])||isset($v['windspeed'])){
        insertWeather($conn,$unit,$d);
        if(isset($v['raw data']))telemetry($conn,$unit,'radiation',(float)$v['raw data']);
        return;
    }

    $keys=array_keys($v);
    $isInv=$task==='inverter'||strpos($dev,'inverter')!==false;
    if(!$isInv){
        foreach($keys as $key){
            $l=strtolower((string)$key);
            if(preg_match('/active.*power|ac.*power|power.*ac|a\.c\..*power/',$l)&&!preg_match('/reactive|apparent|3.phase/',$l)){$isInv=true;break;}
            if(preg_match('/\d/',$l)&&preg_match('/curr|current|amp/',$l)&&!preg_match('/phase|3.phase|reactive|apparent|freq|temp/',$l)){$isInv=true;break;}
        }
    }
    if($isInv){
        insertInverter($conn,$unit,$d);
        $power=inverterPower($v);
        if($power>0)telemetry($conn,$unit,'inverter_power',$power);
    }
}

$plants=array_keys(plant_catalog());
$allowed=array_fill_keys($plants,true);
$runSeconds=(int)(getenv('RUN_SECONDS')?:($_GET['run_seconds']??0));
$started=time();
$clients=[];
$reconnectAt=array_fill_keys($plants,0);

while(true){
    if($runSeconds>0&&time()-$started>=$runSeconds)break;

    foreach($plants as $plant){
        if(isset($clients[$plant])||time()<$reconnectAt[$plant])continue;
        $client=new PlantWSClient('161.97.87.75',5000,'/');
        if(!$client->connect()){$reconnectAt[$plant]=time()+5;continue;}
        $client->send(json_encode(['type'=>'subscribe','unit_id'=>$plant]));
        $clients[$plant]=$client;
        echo "[WS] subscribed $plant on dedicated connection\n";
    }

    $read=[];$socketToPlant=[];
    foreach($clients as $plant=>$client){
        $socket=$client->socket();
        if(!is_resource($socket)){unset($clients[$plant]);$reconnectAt[$plant]=time()+5;continue;}
        $read[]=$socket;
        $socketToPlant[(int)$socket]=$plant;
    }

    if(!$read){usleep(250000);continue;}
    $write=null;$except=null;
    $ready=@stream_select($read,$write,$except,1,0);
    if($ready===false){usleep(100000);continue;}
    if($ready===0)continue;

    foreach($read as $socket){
        $plant=$socketToPlant[(int)$socket]??null;
        if($plant===null||!isset($clients[$plant]))continue;
        $frame=$clients[$plant]->read();
        if($frame===null||$frame['op']==='close'){
            $clients[$plant]->close();
            unset($clients[$plant]);
            $reconnectAt[$plant]=time()+5;
            echo "[WS] reconnect scheduled for $plant\n";
            continue;
        }
        if($frame['op']!=='text'||$frame['payload']==='')continue;
        $d=json_decode($frame['payload'],true);
        if(!is_array($d))continue;
        processPlantMessage($conn,$plant,$allowed,$d);
    }
}

foreach($clients as $client)$client->close();
