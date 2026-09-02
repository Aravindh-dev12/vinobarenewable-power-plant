<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/plant_config.php';

function failJson(string $msg, int $code=400): void { http_response_code($code); echo json_encode(['success'=>false,'error'=>$msg]); exit; }
function bearer(): string { $h=$_SERVER['HTTP_AUTHORIZATION']??''; if(function_exists('getallheaders')){$a=getallheaders();$h=$a['Authorization']??$a['authorization']??$h;} return preg_match('/Bearer\s+(\S+)/i',$h,$m)?$m[1]:''; }
function floorLabel(int $ts,int $mins): string { $h=(int)date('H',$ts);$m=(int)date('i',$ts);$m=(int)(floor($m/$mins)*$mins);return sprintf('%02d:%02d',$h,$m); }
function dailyBuckets(int $mins): array {
    $rows=[]; $start=5*60+($mins===60?0:30); $end=19*60+($mins===60?0:30);
    for($t=$start;$t<=$end;$t+=$mins){$label=sprintf('%02d:%02d',intdiv($t,60),$t%60);$rows[$label]=['time_label'=>$label,'vcb_kwh'=>0.0,'vcb_kw'=>0.0];}
    return $rows;
}
function monthlyBuckets(string $ym): array {
    $rows=[];$first=strtotime($ym.'-01');$days=(int)date('t',$first);for($d=1;$d<=$days;$d++){ $ts=strtotime(sprintf('%s-%02d',$ym,$d));$label=date('d-m-Y',$ts);$rows[$label]=['time_label'=>$label,'vcb_kwh'=>0.0,'vcb_kw'=>0.0]; }return $rows;
}
function finalizeRows(array &$rows,int $count,bool $htAvailable): void {
    foreach($rows as &$r){$totalKwh=0.0;$totalKw=0.0;for($i=1;$i<=$count;$i++){$totalKwh+=(float)($r["inv{$i}_kwh"]??0);$totalKw+=(float)($r["inv{$i}_kw"]??0);} $r['inv_total_kwh']=$totalKwh;$r['inv_total_kw']=$totalKw;$r['tx_loss']=$htAvailable?($totalKwh-(float)($r['vcb_kwh']??0)):null;}
    unset($r);
}

try {
    $token=trim((string)($_GET['token']??bearer())); if($token==='')failJson('Authentication required',401);
    $st=$conn->prepare('SELECT id,email,role,plant_id FROM users WHERE auth_token=? LIMIT 1');$st->bind_param('s',$token);$st->execute();$res=$st->get_result();$user=$res&&$res->num_rows?$res->fetch_assoc():null;$st->close();if(!$user)failJson('Invalid session',401);
    migrate_user_plant_alias($conn,$user);

    $type=(string)($_GET['type']??'daily'); if(!in_array($type,['daily','monthly'],true))failJson('Invalid report type');
    $date=trim((string)($_GET['date']??date($type==='daily'?'Y-m-d':'Y-m')));
    if($type==='daily'&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))failJson('Use YYYY-MM-DD for daily report');
    if($type==='monthly'&&!preg_match('/^\d{4}-\d{2}$/',$date))failJson('Use YYYY-MM for monthly report');
    $plantRaw=trim((string)($_GET['plant']??''));$plant=$plantRaw==='all'?'all':normalize_plant_id($plantRaw);
    if(($user['role']??'user')!=='admin')$plant=normalize_plant_id((string)$user['plant_id']);
    if($plant!=='all'&&!is_valid_plant_id($plant))$plant='vinoba-1';
    $catalog=plant_catalog();$selectedIds=$plant==='all'?array_keys($catalog):[$plant];
    $escapedIds=array_map(fn($x)=>"'".$conn->real_escape_string($x)."'",$selectedIds);$idList=implode(',',$escapedIds);$plantClause="plant_id IN ($idList)";
    $chart=isset($_GET['chart'])&&$_GET['chart']=='1';$bucketMinutes=$chart?60:15;
    $rows=$type==='daily'?dailyBuckets($bucketMinutes):monthlyBuckets($date);

    $periodStart=$type==='daily'?$date.' 00:00:00':$date.'-01 00:00:00';
    $periodEnd=$type==='daily'?date('Y-m-d 00:00:00',strtotime($date.' +1 day')):date('Y-m-d 00:00:00',strtotime($date.'-01 +1 month'));
    $startEsc=$conn->real_escape_string($periodStart);$endEsc=$conn->real_escape_string($periodEnd);

    $series=[];$seen=[];
    $q="SELECT DISTINCT plant_id,device_name FROM inverter_readings WHERE recorded_at>='$startEsc' AND recorded_at<'$endEsc' AND $plantClause ORDER BY plant_id,device_name";
    if($r=$conn->query($q)){while($x=$r->fetch_assoc()){ $dev=trim((string)$x['device_name']);if($dev==='')continue;$key=$x['plant_id']."\x1F".$dev;if(isset($seen[$key]))continue;$seen[$key]=true;$label=$plant==='all'?(($catalog[$x['plant_id']]['name']??$x['plant_id']).' / '.$dev):$dev;$series[]=['key'=>$key,'plant_id'=>$x['plant_id'],'device'=>$dev,'label'=>$label];}}
    if(!$series){$q="SELECT plant_id,device_name,MAX(recorded_at) mx FROM inverter_readings WHERE $plantClause GROUP BY plant_id,device_name ORDER BY plant_id,device_name";if($r=$conn->query($q)){while($x=$r->fetch_assoc()){ $dev=trim((string)$x['device_name']);if($dev==='')continue;$key=$x['plant_id']."\x1F".$dev;$label=$plant==='all'?(($catalog[$x['plant_id']]['name']??$x['plant_id']).' / '.$dev):$dev;$series[]=['key'=>$key,'plant_id'=>$x['plant_id'],'device'=>$dev,'label'=>$label];}}}
    $seriesMap=[];$invNames=[];foreach($series as $i=>$s){$idx=$i+1;$seriesMap[$s['key']]=$idx;$invNames[]=$s['label'];foreach($rows as &$row){$row["inv{$idx}_kwh"]=0.0;$row["inv{$idx}_kw"]=0.0;$row["inv{$idx}_temp"]=0.0;}unset($row);}

    $htAvailable=false;
    if($type==='daily'){
        $dateEsc=$conn->real_escape_string($date);
        $q="SELECT plant_id,device_name,recorded_at,daily_generation,ac_active_power,internal_temp FROM inverter_readings WHERE DATE(recorded_at)='$dateEsc' AND $plantClause ORDER BY recorded_at ASC";
        if($r=$conn->query($q)){while($x=$r->fetch_assoc()){ $key=$x['plant_id']."\x1F".trim((string)$x['device_name']);if(!isset($seriesMap[$key]))continue;$idx=$seriesMap[$key];$ts=strtotime($x['recorded_at']);$label=$chart?date('H:00',$ts):floorLabel($ts,$bucketMinutes);if(!isset($rows[$label]))continue;$rows[$label]["inv{$idx}_kwh"]=(float)$x['daily_generation'];$rows[$label]["inv{$idx}_kw"]=(float)$x['ac_active_power'];$rows[$label]["inv{$idx}_temp"]=(float)$x['internal_temp'];}}
        $vcbBy=[];$base=[];$q="SELECT plant_id,recorded_at,active_power_total,active_total_export FROM vcb_readings WHERE DATE(recorded_at)='$dateEsc' AND $plantClause ORDER BY plant_id,recorded_at ASC";
        if($r=$conn->query($q)){while($x=$r->fetch_assoc()){$htAvailable=true;$pid=$x['plant_id'];$exp=(float)$x['active_total_export'];if(!isset($base[$pid]))$base[$pid]=$exp;$delta=max($exp-$base[$pid],0)/1000.0;$ts=strtotime($x['recorded_at']);$label=$chart?date('H:00',$ts):floorLabel($ts,$bucketMinutes);if(!isset($rows[$label]))continue;$vcbBy[$label][$pid]=['kwh'=>$delta,'kw'=>(float)$x['active_power_total']];}}
        foreach($vcbBy as $label=>$plants){$kwh=0;$kw=0;foreach($plants as $v){$kwh+=$v['kwh'];$kw+=$v['kw'];}$rows[$label]['vcb_kwh']=$kwh;$rows[$label]['vcb_kw']=$kw;}
    } else {
        $q="SELECT DATE(recorded_at) day,plant_id,device_name,MAX(daily_generation) kwh,MAX(ac_active_power) kw,MAX(internal_temp) temp FROM inverter_readings WHERE recorded_at>='$startEsc' AND recorded_at<'$endEsc' AND $plantClause GROUP BY DATE(recorded_at),plant_id,device_name ORDER BY day,plant_id,device_name";
        if($r=$conn->query($q)){while($x=$r->fetch_assoc()){ $key=$x['plant_id']."\x1F".trim((string)$x['device_name']);if(!isset($seriesMap[$key]))continue;$idx=$seriesMap[$key];$label=date('d-m-Y',strtotime($x['day']));if(!isset($rows[$label]))continue;$rows[$label]["inv{$idx}_kwh"]=(float)$x['kwh'];$rows[$label]["inv{$idx}_kw"]=(float)$x['kw'];$rows[$label]["inv{$idx}_temp"]=(float)$x['temp'];}}
        $q="SELECT day,SUM(kwh_exp) kwh_exp,SUM(max_kw) max_kw FROM (SELECT DATE(recorded_at) day,plant_id,GREATEST(MAX(active_total_export)-MIN(active_total_export),0) kwh_exp,MAX(active_power_total) max_kw FROM vcb_readings WHERE recorded_at>='$startEsc' AND recorded_at<'$endEsc' AND $plantClause GROUP BY DATE(recorded_at),plant_id) x GROUP BY day ORDER BY day";
        if($r=$conn->query($q)){while($x=$r->fetch_assoc()){$htAvailable=true;$label=date('d-m-Y',strtotime($x['day']));if(isset($rows[$label])){$rows[$label]['vcb_kwh']=(float)$x['kwh_exp']/1000.0;$rows[$label]['vcb_kw']=(float)$x['max_kw'];}}}
    }
    finalizeRows($rows,count($series),$htAvailable);

    $oper=['inverter'=>['start'=>null,'end'=>null],'vcb'=>['start'=>null,'end'=>null],'transformer'=>['start'=>null,'end'=>null]];
    if($type==='daily'){$dateEsc=$conn->real_escape_string($date);$queries=[
        'inverter'=>"SELECT DATE_FORMAT(MIN(recorded_at),'%H:%i') s,DATE_FORMAT(MAX(recorded_at),'%H:%i') e FROM inverter_readings WHERE DATE(recorded_at)='$dateEsc' AND ac_active_power>0 AND $plantClause",
        'vcb'=>"SELECT DATE_FORMAT(MIN(recorded_at),'%H:%i') s,DATE_FORMAT(MAX(recorded_at),'%H:%i') e FROM vcb_readings WHERE DATE(recorded_at)='$dateEsc' AND active_power_total>0 AND $plantClause",
        'transformer'=>"SELECT DATE_FORMAT(MIN(recorded_at),'%H:%i') s,DATE_FORMAT(MAX(recorded_at),'%H:%i') e FROM transformer_readings WHERE DATE(recorded_at)='$dateEsc' AND $plantClause"];
        foreach($queries as $k=>$sql){if($r=$conn->query($sql)){if($x=$r->fetch_assoc())$oper[$k]=['start'=>$x['s']?:null,'end'=>$x['e']?:null];}}
    }
    $latest=null;if($type==='daily'){$dateEsc=$conn->real_escape_string($date);$q="SELECT MAX(recorded_at) mx FROM (SELECT recorded_at FROM inverter_readings WHERE DATE(recorded_at)='$dateEsc' AND $plantClause UNION ALL SELECT recorded_at FROM vcb_readings WHERE DATE(recorded_at)='$dateEsc' AND $plantClause) z";if($r=$conn->query($q)){if($x=$r->fetch_assoc())$latest=$x['mx'];}}
    $isToday=$type==='daily'&&$date===date('Y-m-d');$fresh=$latest&&(time()-strtotime($latest)<=600);
    $plantInfo=$plant==='all'?null:$catalog[$plant];
    echo json_encode(['success'=>true,'meta'=>['type'=>$type,'date'=>$date,'plant'=>$plant,'plant_name'=>$plant==='all'?'All Plants':$plantInfo['name'],'service_number'=>$plant==='all'?'':$plantInfo['service_number'],'source'=>($isToday&&$fresh)?'db_live':'db_cache','latest_at'=>$latest,'inv_names'=>$invNames,'ht_available'=>$htAvailable,'operating_times'=>$oper,'generated_at'=>date('Y-m-d H:i:s')],'data'=>array_values($rows)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){error_log('[api_reports] '.$e->getMessage());failJson('Server error: '.$e->getMessage(),500);}
