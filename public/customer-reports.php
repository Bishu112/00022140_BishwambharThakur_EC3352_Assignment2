<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role(['customer']);
$today=new DateTimeImmutable('today');$range=$_GET['range']??'monthly';
$parse=static function($value){$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value?:'');return $date&&$date->format('Y-m-d')===$value?$date:null;};
if($range==='daily'){$from=$today;$to=$today;}elseif($range==='weekly'){$from=$today->modify('-6 days');$to=$today;}elseif($range==='monthly'){$from=$today->modify('-29 days');$to=$today;}elseif($range==='custom'){$from=$parse($_GET['from']??'');$to=$parse($_GET['to']??'');if(!$from||!$to||$to<$from){flash('Choose a valid custom date range.');$range='monthly';$from=$today->modify('-29 days');$to=$today;}}else{$range='monthly';$from=$today->modify('-29 days');$to=$today;}
$fromSql=$from->format('Y-m-d 00:00:00');$toSql=$to->modify('+1 day')->format('Y-m-d 00:00:00');$customerId=(int)signed_in()['user_id'];
$st=$pdo->prepare('SELECT COUNT(t.transaction_id) transaction_count,COALESCE(SUM(t.amount),0) spend FROM transactions t JOIN orders o ON o.order_id=t.order_id WHERE o.customer_id=? AND t.created_at>=? AND t.created_at<?');$st->execute([$customerId,$fromSql,$toSql]);$summary=$st->fetch();
$st=$pdo->prepare('SELECT o.order_id,o.status,o.fulfillment,o.phone customer_phone,t.payment_method,t.payment_status,t.amount,t.created_at,COALESCE(SUM(i.quantity),0) units FROM transactions t JOIN orders o ON o.order_id=t.order_id LEFT JOIN order_items i ON i.order_id=o.order_id WHERE o.customer_id=? AND t.created_at>=? AND t.created_at<? GROUP BY t.transaction_id ORDER BY t.created_at DESC');$st->execute([$customerId,$fromSql,$toSql]);$transactions=$st->fetchAll();$qty=0;foreach($transactions as $row)$qty+=(int)$row['units'];
if(($_GET['export']??'')==='csv'){
    $user=signed_in();
    $filename='redline-customer-statement-'.$from->format('Y-m-d').'_'.$to->format('Y-m-d').'.csv';
    csv_header($filename);
    $out=csv_open();
    // Statement header, modelled on the HelloService merchant statement.
    csv_row($out, ['REDLINE ROYALE','','','','','']);
    csv_row($out, ['Customer Name:', $user['full_name'], 'Email:', $user['email'],'','']);
    csv_row($out, ['Invoice Date:', date('Y-m-d H:i:s'),'','','','']);
    csv_row($out, ['Customer Name','Customer Phone','Status','Created Date','Amount/NPR','Fees/NPR']);
    $txnTotal=0.0;$txnFees=0.0;
    if($transactions){
        foreach($transactions as $row){
            $fee=$row['fulfillment']==='delivery'?150:0;
            $txnTotal+=(float)$row['amount'];$txnFees+=$fee;
            csv_row($out,[$user['full_name'],$row['customer_phone'],ucfirst($row['status']),date('d-m-Y H:i:s',strtotime($row['created_at'])),csv_money($row['amount']),csv_money($fee)]);
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
    $user=signed_in();
    $filename='redline-customer-statement-'.$from->format('Y-m-d').'_'.$to->format('Y-m-d').'.xls';
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
    xlsx_style($doc,'AltCenter',['font'=>['name'=>'Arial','size'=>10,'color'=>'#292528'],'fill'=>'#F6F4FA','border'=>$line,'align'=>['horizontal'=>'Center','vertical'=>'Center']]);
    xlsx_style($doc,'TotalLabel',['font'=>['name'=>'Arial','size'=>10,'bold'=>true,'color'=>'#292528'],'fill'=>$lav,'border'=>$line]);
    xlsx_style($doc,'Total',['font'=>['name'=>'Arial','size'=>10,'bold'=>true,'color'=>'#292528'],'fill'=>$lav,'border'=>$line,'numberFormat'=>'#,##0.00','align'=>['horizontal'=>'Right']]);
    xlsx_style($doc,'NetHeader',['font'=>['name'=>'Arial','size'=>10,'bold'=>true,'color'=>'#FFFFFF'],'fill'=>$band,'border'=>$bandDark,'align'=>['horizontal'=>'Center']]);
    xlsx_style($doc,'NetValue',['font'=>['name'=>'Arial','size'=>10,'bold'=>true,'color'=>'#292528'],'fill'=>$lav,'border'=>$line,'numberFormat'=>'#,##0.00','align'=>['horizontal'=>'Center']]);
    xlsx_style($doc,'Signature',['font'=>['name'=>'Arial','size'=>10,'bold'=>true,'color'=>'#292528'],'fill'=>'#EDEDED','border'=>'#B9B3C4','align'=>['horizontal'=>'Center','vertical'=>'Center']]);
    xlsx_style($doc,'Footer',['font'=>['name'=>'Arial','size'=>9,'italic'=>true,'color'=>'#71696C']]);
    xlsx_style($doc,'Empty',['font'=>['name'=>'Arial','size'=>10,'italic'=>true,'color'=>'#71696C'],'border'=>$line]);
    xlsx_columns($doc,[150,115,95,130,95,95]);
    // Brand banner, then the statement block on the right.
    xlsx_row($doc,[['value'=>'REDLINE ROYALE','style'=>'Brand','merge'=>5]],28);
    xlsx_row($doc,[['value'=>'Customer statement','style'=>'Title','merge'=>5]]);
    xlsx_row($doc,[['value'=>'Customer Name:','style'=>'MetaLabel'],['value'=>$user['full_name'],'style'=>'MetaValue','merge'=>4]]);
    xlsx_row($doc,[['value'=>'Email:','style'=>'MetaLabel'],['value'=>$user['email'],'style'=>'MetaValue','merge'=>4]]);
    xlsx_row($doc,[['value'=>'Invoice Date:','style'=>'MetaLabel'],['value'=>date('Y-m-d H:i:s'),'style'=>'MetaValue','merge'=>4]]);
    xlsx_row($doc,[]);
    // Statement table
    xlsx_row($doc,[['value'=>'Customer Name','style'=>'Header'],['value'=>'Customer Phone','style'=>'Header'],['value'=>'Status','style'=>'Header'],['value'=>'Created Date','style'=>'Header'],['value'=>'Amount/NPR','style'=>'Header'],['value'=>'Fees/NPR','style'=>'Header']]);
    $txnTotal=0.0;$txnFees=0.0;$i=0;
    if($transactions){
        foreach($transactions as $row){
            $fee=$row['fulfillment']==='delivery'?150:0;
            $txnTotal+=(float)$row['amount'];$txnFees+=$fee;
            xlsx_row($doc,[
                ['value'=>$user['full_name'],'style'=>$i%2?'Alt':'Data'],
                ['value'=>$row['customer_phone'],'style'=>$i%2?'AltCenter':'DataCenter'],
                ['value'=>ucfirst($row['status']),'style'=>$i%2?'AltCenter':'DataCenter'],
                ['value'=>date('d-m-Y H:i:s',strtotime($row['created_at'])),'style'=>$i%2?'AltCenter':'DataCenter'],
                ['value'=>(float)$row['amount'],'style'=>$i%2?'Alt':'Data'],
                ['value'=>$fee,'style'=>$i%2?'Alt':'Data'],
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
    // Net amount / total fees summary boxes
    xlsx_row($doc,[['value'=>'Net Amount','style'=>'NetHeader','merge'=>2],['value'=>'Total Fees','style'=>'NetHeader','merge'=>2]]);
    xlsx_row($doc,[['value'=>$txnTotal-$txnFees,'style'=>'NetValue','merge'=>2],['value'=>$txnFees,'style'=>'NetValue','merge'=>2]]);
    xlsx_row($doc,[]);
    xlsx_row($doc,[['value'=>'Accounting Signature:','style'=>'Signature','merge'=>2],['value'=>'Merchant Signature:','style'=>'Signature','merge'=>2]],28);
    xlsx_render($doc,$filename);
}
$pageTitle='Purchase report';$workspaceType='customer';$workspaceActive='Reports';include __DIR__.'/../includes/header.php';
?>
<div class="page-kicker">REPORT PAGE</div><h1 class="page-heading">Your order activity</h1>
<nav class="report-period-tabs" aria-label="Report period">
<?php foreach(['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly'] as $key=>$label):?><a class="<?= $range===$key?'is-selected':'' ?>" href="?range=<?=$key?>"><?=$label?></a><?php endforeach;?>
<a class="<?= $range==='custom'?'is-selected':'' ?>" href="?range=custom&amp;from=<?=e($_GET['from']??'')?>&amp;to=<?=e($_GET['to']??'')?>">Custom date range</a>
</nav>
<?php if($range==='custom'):?><form method="get" class="report-range-form"><input type="hidden" name="range" value="custom"><label for="from">From</label><input id="from" type="date" name="from" value="<?=e($_GET['from']??'')?>" required><label for="to">To</label><input id="to" type="date" name="to" value="<?=e($_GET['to']??'')?>" required><button type="submit">Apply</button></form><?php endif;?>
<section class="customer-report-stats" aria-label="Order activity summary"><article class="report-stat"><span>TOTAL PRODUCTS ORDERED</span><strong><?=$qty?> items</strong></article><article class="report-stat"><span>TRANSACTIONS IN RANGE</span><strong><?=(int)$summary['transaction_count']?> orders · Rs. <?=number_format((float)$summary['spend'],2)?></strong></article></section>
<section class="customer-report-history"><h2>Transaction history</h2><?php if($transactions):?><div class="table-scroll" role="region" aria-label="Transaction history" tabindex="0"><table><thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Status</th><th>Total</th></tr></thead><tbody><?php foreach($transactions as $row):?><tr><td>AB-<?=str_pad((string)$row['order_id'],5,'0',STR_PAD_LEFT)?></td><td><?=e(date('d M',strtotime($row['created_at'])))?></td><td><?=(int)$row['units']?></td><td><?=e(ucfirst($row['status']))?></td><td>Rs. <?=number_format((float)$row['amount'],2)?></td></tr><?php endforeach;?></tbody></table></div><?php else:?><p class="muted">No orders were placed in this period.</p><?php endif;?></section>
<p class="export-bar"><a class="button" href="?range=<?=e($range)?>&amp;from=<?=e($_GET['from']??'')?>&amp;to=<?=e($_GET['to']??'')?>&amp;export=csv">Download report</a> <a class="button button-secondary" href="?range=<?=e($range)?>&amp;from=<?=e($_GET['from']??'')?>&amp;to=<?=e($_GET['to']??'')?>&amp;export=xlsx">Download Excel statement</a></p>
<?php include __DIR__.'/../includes/footer.php';?>
