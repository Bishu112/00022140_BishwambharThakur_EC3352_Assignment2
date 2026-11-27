<?php
require_once __DIR__.'/../includes/bootstrap.php';require_role(['vendor','admin']);
$user=signed_in();$isAdmin=$user['role']==='admin';$vendorId=(int)$user['user_id'];$today=new DateTimeImmutable('today');$range=$_GET['range']??'monthly';
$validDate=static function($value){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$value?:'');return $d&&$d->format('Y-m-d')===$value?$d:null;};
if($range==='daily'){$from=$today;$to=$today;}elseif($range==='weekly'){$from=$today->modify('-6 days');$to=$today;}elseif($range==='monthly'){$from=$today->modify('-29 days');$to=$today;}elseif($range==='custom'){$rawFrom=trim((string)($_GET['from']??''));$rawTo=trim((string)($_GET['to']??''));if($rawFrom===''&&$rawTo===''){$from=$today->modify('-29 days');$to=$today;}else{$from=$validDate($rawFrom);$to=$validDate($rawTo);if(!$from||!$to||$to<$from||$from->diff($to)->days>92){flash('Choose a valid date range of up to 93 days.');$range='monthly';$from=$today->modify('-29 days');$to=$today;}}}else{$range='monthly';$from=$today->modify('-29 days');$to=$today;}
$fromSql=$from->format('Y-m-d 00:00:00');$toSql=$to->modify('+1 day')->format('Y-m-d 00:00:00');$scopeProduct=$isAdmin?'1=1':'vendor_id=?';$scopeProductArgs=$isAdmin?[]:[$vendorId];$scopeView=$isAdmin?'1=1':'p.vendor_id=?';$scopeSales=$isAdmin?'1=1':'i.vendor_id=?';
$st=$pdo->prepare("SELECT COUNT(*) FROM products WHERE $scopeProduct AND created_at>=? AND created_at<?");$st->execute(array_merge($scopeProductArgs,[$fromSql,$toSql]));$listed=(int)$st->fetchColumn();$st=$pdo->prepare("SELECT COUNT(*) FROM product_views v JOIN products p ON p.product_id=v.product_id WHERE $scopeView AND v.viewed_at>=? AND v.viewed_at<?");$st->execute(array_merge($isAdmin?[]:[$vendorId],[$fromSql,$toSql]));$views=(int)$st->fetchColumn();$st=$pdo->prepare("SELECT COALESCE(SUM(i.quantity),0) units,COALESCE(SUM(i.quantity*i.unit_price),0) gross,COUNT(DISTINCT o.order_id) orders_count FROM orders o JOIN order_items i ON i.order_id=o.order_id WHERE $scopeSales AND o.created_at>=? AND o.created_at<? AND o.status<>'discarded'");$st->execute(array_merge($isAdmin?[]:[$vendorId],[$fromSql,$toSql]));$sales=$st->fetch();$st=$pdo->prepare("SELECT p.product_id,p.title,COUNT(v.view_id) view_count FROM products p LEFT JOIN product_views v ON v.product_id=p.product_id AND v.viewed_at>=? AND v.viewed_at<? WHERE $scopeView GROUP BY p.product_id HAVING view_count>0 ORDER BY view_count DESC,p.title LIMIT 5");$st->execute(array_merge([$fromSql,$toSql],$isAdmin?[]:[$vendorId]));$popular=$st->fetchAll();
$txnAmount=$isAdmin?'t.amount':'(SELECT COALESCE(SUM(ri.quantity*ri.unit_price),0) FROM order_items ri WHERE ri.order_id=o.order_id AND ri.vendor_id=?)';
$txnSql="SELECT t.created_at,$txnAmount amount,t.payment_status,o.order_id,o.status,o.phone customer_phone,o.fulfillment,u.full_name customer FROM transactions t JOIN orders o ON o.order_id=t.order_id JOIN users u ON u.user_id=o.customer_id WHERE t.created_at>=? AND t.created_at<?".($isAdmin?'':' AND EXISTS(SELECT 1 FROM order_items i WHERE i.order_id=o.order_id AND i.vendor_id=?)')." ORDER BY t.created_at DESC";
$st=$pdo->prepare($txnSql);$st->execute($isAdmin?[$fromSql,$toSql]:[$vendorId,$fromSql,$toSql,$vendorId]);$txnRows=$st->fetchAll();
$businessName='';$businessAddress='';
if(!$isAdmin){$st=$pdo->prepare('SELECT business_name,address AS business_address FROM vendor_profiles WHERE user_id=?');$st->execute([$vendorId]);$profile=$st->fetch()?:[];$businessName=(string)($profile['business_name']??$user['full_name']);$businessAddress=(string)($profile['business_address']??'');}
if(($_GET['export']??'')==='csv'){
    $scopeLabel=$isAdmin?'All vendors':$businessName;
    $location=$isAdmin?'Kathmandu, Nepal':($businessAddress?:'Nepal');
    $filename='redline-'.($isAdmin?'admin':'vendor').'-statement-'.$from->format('Y-m-d').'_'.$to->format('Y-m-d').'.csv';
    csv_header($filename);
    $out=csv_open();
    // Statement header, modelled on the HelloService merchant statement.
    csv_row($out, ['REDLINE ROYALE','','','','','']);
    csv_row($out, ['Merchant Name:', $scopeLabel, 'Location:', $location,'','']);
    csv_row($out, ['Invoice Date:', date('Y-m-d H:i:s'),'','','','']);
    csv_row($out, ['Customer Name','Customer Phone','Status','Created Date','Amount/NPR','Fees/NPR']);
    $txnTotal=0.0;$txnFees=0.0;
    if($txnRows){
        foreach($txnRows as $r){
            $fee=$r['fulfillment']==='delivery'?150:0;
            $txnTotal+=(float)$r['amount'];$txnFees+=$fee;
            csv_row($out,[$r['customer'],$r['customer_phone'],ucfirst($r['status']),date('d-m-Y H:i:s',strtotime($r['created_at'])),csv_money($r['amount']),csv_money($fee)]);
        }
    }else{csv_row($out,['No transactions in this period.','','','','','']);}

    csv_row($out,['Total=','','','',csv_money($txnTotal),csv_money($txnFees)]);
    csv_row($out, ['Net Amount', 'Total Fees','','','','']);
    csv_row($out,[csv_money($txnTotal-$txnFees),csv_money($txnFees),'','','','']);
    csv_row($out,['Accounting Signature:', 'Merchant Signature:','','','','']);
    fclose($out);
    exit;
}
elseif(($_GET['export']??'')==='xlsx'){
    $scopeLabel=$isAdmin?'All vendors':$businessName;
    $location=$isAdmin?'Kathmandu, Nepal':($businessAddress?:'Nepal');
    $filename='redline-'.($isAdmin?'admin':'vendor').'-statement-'.$from->format('Y-m-d').'_'.$to->format('Y-m-d').'.xls';
    $doc=xlsx_open();
    // Reference palette: HelloService merchant statement (purple bands, lavender totals)
    $band='#5B2C83';$bandDark='#4A226B';$lav='#DCD5E8';$line='#8E7FA8';
    xlsx_style($doc,'Brand',['font'=>['name'=>'Arial','size'=>18,'bold'=>true,'color'=>'#FFFFFF'],'fill'=>'#B31B34','align'=>['horizontal'=>'Center','vertical'=>'Center']]);
    xlsx_style($doc,'Title',['font'=>['name'=>'Arial','size'=>15,'bold'=>true,'color'=>'#5B2C83'],'align'=>['horizontal'=>'Center','vertical'=>'Center']]);
    xlsx_style($doc,'MetaLabel',['font'=>['name'=>'Arial','size'=>10,'bold'=>true,'color'=>'#292528']]);
    xlsx_style($doc,'MetaValue',['font'=>['name'=>'Arial','size'=>10,'color'=>'#292528']]);
    xlsx_style($doc,'Section',['font'=>['name'=>'Arial','size'=>10,'bold'=>true,'color'=>'#FFFFFF'],'fill'=>$band]);
    xlsx_style($doc,'Header',['font'=>['name'=>'Arial','size'=>10,'bold'=>true,'color'=>'#FFFFFF'],'fill'=>$band,'border'=>$bandDark,'align'=>['horizontal'=>'Center','vertical'=>'Center','wrap'=>true]]);
    xlsx_style($doc,'Data',['font'=>['name'=>'Arial','size'=>10,'color'=>'#292528'],'border'=>$line,'align'=>['vertical'=>'Center']]);
    xlsx_style($doc,'DataCenter',['font'=>['name'=>'Arial','size'=>10,'color'=>'#292528'],'border'=>$line,'align'=>['horizontal'=>'Center','vertical'=>'Center']]);
    xlsx_style($doc,'Alt',['font'=>['name'=>'Arial','size'=>10,'color'=>'#292528'],'fill'=>'#F6F4FA','border'=>$line,'align'=>['vertical'=>'Center']]);
    xlsx_style($doc,'TotalLabel',['font'=>['name'=>'Arial','size'=>10,'bold'=>true,'color'=>'#292528'],'fill'=>$lav,'border'=>$line]);
    xlsx_style($doc,'Total',['font'=>['name'=>'Arial','size'=>10,'bold'=>true,'color'=>'#292528'],'fill'=>$lav,'border'=>$line,'numberFormat'=>'#,##0.00','align'=>['horizontal'=>'Right']]);
    xlsx_style($doc,'NetHeader',['font'=>['name'=>'Arial','size'=>10,'bold'=>true,'color'=>'#FFFFFF'],'fill'=>$band,'border'=>$bandDark,'align'=>['horizontal'=>'Center']]);
    xlsx_style($doc,'NetValue',['font'=>['name'=>'Arial','size'=>10,'bold'=>true,'color'=>'#292528'],'fill'=>$lav,'border'=>$line,'numberFormat'=>'#,##0.00','align'=>['horizontal'=>'Center']]);
    xlsx_style($doc,'Signature',['font'=>['name'=>'Arial','size'=>10,'bold'=>true,'color'=>'#292528'],'fill'=>'#EDEDED','border'=>'#B9B3C4','align'=>['horizontal'=>'Center','vertical'=>'Center']]);
    xlsx_style($doc,'Footer',['font'=>['name'=>'Arial','size'=>9,'italic'=>true,'color'=>'#71696C']]);
    xlsx_style($doc,'Empty',['font'=>['name'=>'Arial','size'=>10,'italic'=>true,'color'=>'#71696C'],'border'=>$line]);
    xlsx_columns($doc,[150,115,95,130,95,95]);
    // Logo / brand banner, then merchant block on the right.
    xlsx_row($doc,[['value'=>'REDLINE ROYALE','style'=>'Brand','merge'=>5]],28);
    xlsx_row($doc,[['value'=>$isAdmin?'Merchant statement - all vendors':'Merchant statement','style'=>'Title','merge'=>5]]);
    xlsx_row($doc,[['value'=>'Merchant Name:','style'=>'MetaLabel'],['value'=>$scopeLabel,'style'=>'MetaValue','merge'=>4]]);
    xlsx_row($doc,[['value'=>'Location:','style'=>'MetaLabel'],['value'=>$location,'style'=>'MetaValue','merge'=>4]]);
    xlsx_row($doc,[['value'=>'Invoice Date:','style'=>'MetaLabel'],['value'=>date('Y-m-d H:i:s'),'style'=>'MetaValue','merge'=>4]]);
    xlsx_row($doc,[]);
    // Statement table
    xlsx_row($doc,[['value'=>'Customer Name','style'=>'Header'],['value'=>'Customer Phone','style'=>'Header'],['value'=>'Status','style'=>'Header'],['value'=>'Created Date','style'=>'Header'],['value'=>'Amount/NPR','style'=>'Header'],['value'=>'Fees/NPR','style'=>'Header']]);
    $txnTotal=0.0;$txnFees=0.0;$i=0;
    if($txnRows){
        foreach($txnRows as $r){
            $fee=$r['fulfillment']==='delivery'?150:0;
            $txnTotal+=(float)$r['amount'];$txnFees+=$fee;
            $rowStyle=$i%2?'Alt':'Data';
            xlsx_row($doc,[
                ['value'=>$r['customer'],'style'=>$rowStyle],
                ['value'=>$r['customer_phone'],'style'=>$i%2?'Alt':'DataCenter'],
                ['value'=>ucfirst($r['status']),'style'=>$i%2?'Alt':'DataCenter'],
                ['value'=>date('d-m-Y H:i:s',strtotime($r['created_at'])),'style'=>$i%2?'Alt':'DataCenter'],
                ['value'=>(float)$r['amount'],'style'=>$rowStyle],
                ['value'=>$fee,'style'=>$rowStyle],
            ]);
            $i++;
        }
    }else{xlsx_row($doc,[['value'=>'No transactions in this period.','style'=>'Empty','merge'=>5]]);}
    xlsx_row($doc,[
        ['value'=>'Total=','style'=>'TotalLabel'],
        ['value'=>'','style'=>'TotalLabel'],
        ['value'=>'','style'=>'TotalLabel'],
        ['value'=>'','style'=>'TotalLabel'],
        ['value'=>$txnTotal,'style'=>'Total'],
        ['value'=>$txnFees,'style'=>'Total'],
    ]);
    xlsx_row($doc,[]);
    xlsx_row($doc,[['value'=>'Net Amount','style'=>'NetHeader','merge'=>2],['value'=>'Total Fees','style'=>'NetHeader','merge'=>2]]);
    xlsx_row($doc,[['value'=>$txnTotal-$txnFees,'style'=>'NetValue','merge'=>2],['value'=>$txnFees,'style'=>'NetValue','merge'=>2]]);
    xlsx_row($doc,[]);
    xlsx_row($doc,[['value'=>'Accounting Signature:','style'=>'Signature','merge'=>2],['value'=>'Merchant Signature:','style'=>'Signature','merge'=>2]],28);
    xlsx_render($doc,$filename);
}
$chartValues=[];$chartLabels=[];$chartData=[];$chartQuery="SELECT DATE(o.created_at) report_day,COALESCE(SUM(i.quantity*i.unit_price),0) gross FROM orders o JOIN order_items i ON i.order_id=o.order_id WHERE $scopeSales AND o.created_at>=? AND o.created_at<? AND o.status<>'discarded' GROUP BY DATE(o.created_at)";$st=$pdo->prepare($chartQuery);$st->execute(array_merge($isAdmin?[]:[$vendorId],[$fromSql,$toSql]));foreach($st->fetchAll() as $point){$chartData[$point['report_day']]=(float)$point['gross'];}for($day=$from;$day<=$to;$day=$day->modify('+1 day')){$key=$day->format('Y-m-d');$chartLabels[]=$day->format('j M');$chartValues[]=$chartData[$key]??0.0;}$chartMax=max(1.0,...$chartValues);
$pageTitle=$isAdmin?'Admin sales report':'Store performance';if(!$isAdmin){$workspaceType='vendor';$workspaceActive='Reports';}include __DIR__.'/../includes/header.php';
?>
<div class="page-kicker">REPORT PAGE</div><h1 class="page-heading"><?=e($pageTitle)?></h1>
<nav class="report-period-tabs" aria-label="Report period">
<?php foreach(['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly'] as $key=>$label):?><a class="<?=$range===$key?'is-selected':''?>" href="?range=<?=$key?>"><?=$label?></a><?php endforeach;?>
<a class="<?=$range==='custom'?'is-selected':''?>" href="?range=custom">Custom date range</a>
</nav>
<?php if($range==='custom'):?><form method="get" class="report-range-form"><input type="hidden" name="range" value="custom"><label for="from">From</label><input id="from" type="date" name="from" value="<?=e($_GET['from']??'')?>" required><label for="to">To</label><input id="to" type="date" name="to" value="<?=e($_GET['to']??'')?>" required><button type="submit">Apply</button><small>Choose dates within a 93-day period.</small></form><?php endif;?>
<p class="report-date-caption">Showing <?=e($from->format('j M Y'))?> – <?=e($to->format('j M Y'))?> · <?=e($isAdmin?'All vendors':$businessName)?></p>
<section class="workspace-metrics report-metrics" aria-label="Store performance"><article class="workspace-card report-metric-products"><span>Products added</span><strong><?=$listed?></strong><small>Added during this period</small></article><article class="workspace-card report-metric-units"><span>Products sold</span><strong><?=(int)$sales['units']?></strong><small>Units in <?= (int)$sales['orders_count']?> orders</small></article><article class="workspace-card report-metric-popular"><span>Most viewed</span><strong class="report-popular-title"><?=e($popular[0]['title']??'No views yet')?></strong><small><?= $views ?> product views in this period</small></article><article class="workspace-card report-metric-revenue"><span>Revenue</span><strong>Rs. <?=number_format((float)$sales['gross'],2)?></strong><small>From <?= (int)$sales['orders_count']?> non-cancelled orders</small></article></section>
<section class="vendor-report-panels"><article class="workspace-panel report-chart"><div class="report-chart-heading"><div><h2>Sales across selected date range</h2><p>Daily sales from completed order records · Rs.</p></div><strong>Rs. <?=number_format((float)$sales['gross'],2)?></strong></div><div class="report-bars" role="img" aria-label="Daily product sales from <?=e($from->format('j M Y'))?> to <?=e($to->format('j M Y'))?>"><?php foreach($chartValues as $index=>$value):?><div class="report-bar-column"><div class="report-bar" style="height:<?=max(2,(int)round($value/$chartMax*100))?>%" title="<?=e($chartLabels[$index])?> · Rs. <?=number_format($value,2)?>"></div><small><?=e($chartLabels[$index])?></small></div><?php endforeach;?></div><?php if((float)$sales['gross']<=0):?><p class="report-empty-note">No sales recorded in this date range yet. New orders will appear here automatically.</p><?php endif;?></article><article class="workspace-panel report-transaction-panel"><h2>Transaction report</h2><?php if($txnRows):?><ul><?php foreach(array_slice($txnRows,0,6) as $row):?><li><span>AB-<?=str_pad((string)$row['order_id'],5,'0',STR_PAD_LEFT)?></span><strong>Rs. <?=number_format((float)$row['amount'],2)?></strong><small><?=e(date('j M',strtotime($row['created_at'])))?> · <?=e(strtoupper(str_replace('_',' ',$row['payment_status'])))?></small></li><?php endforeach;?></ul><?php else:?><p class="muted">No transactions in this period.</p><?php endif;?></article></section>
<p class="export-bar"><a class="button" href="?range=<?=e($range)?>&amp;from=<?=e($_GET['from']??'')?>&amp;to=<?=e($_GET['to']??'')?>&amp;export=csv">Export report</a> <a class="button button-secondary" href="?range=<?=e($range)?>&amp;from=<?=e($_GET['from']??'')?>&amp;to=<?=e($_GET['to']??'')?>&amp;export=xlsx">Download statement (Excel)</a></p>
<?php include __DIR__.'/../includes/footer.php';?>
