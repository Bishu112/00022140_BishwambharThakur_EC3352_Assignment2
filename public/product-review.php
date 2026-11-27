<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role(['customer']);
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed.');}
check_csrf();
$productId=(int)($_POST['product_id']??0);
$rating=(int)($_POST['rating']??0);
$comment=trim($_POST['comment']??'');
$commentLength=preg_match_all('/./us',$comment);
$customerId=(int)signed_in()['user_id'];
$st=$pdo->prepare("SELECT EXISTS(SELECT 1 FROM order_items i JOIN orders o ON o.order_id=i.order_id WHERE i.product_id=? AND o.customer_id=? AND o.status<>'discarded')");
$st->execute([$productId,$customerId]);
if(!$st->fetchColumn()){
    flash('You can review a product after placing an order for it.');
}elseif($rating<1||$rating>5||$comment===''||$commentLength===false||$commentLength>1000){
    flash('Choose a rating and write a review of 1,000 characters or fewer.');
}else{
    try{
        $st=$pdo->prepare('INSERT INTO reviews(customer_id,product_id,rating,comment) VALUES(?,?,?,?)');
        $st->execute([$customerId,$productId,$rating,$comment]);
        flash('Thank you. Your review has been added.');
    }catch(PDOException $error){
        flash('You have already reviewed this product, or the review could not be saved.');
    }
}
header('Location: product.php?id='.$productId.'#reviews');
exit;
