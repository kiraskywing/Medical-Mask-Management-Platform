<?php 
include "authentication.php";

try {
    if (isset($_POST['createOrder']) && isset($_POST['shop_id']) && isset($_POST['maskPrice']) && isset($_POST['maskAmount'])) {
        $connection->beginTransaction();
        $query = $connection->prepare('select per_mask_price, stock_quantity from shops where shop_id = ' . $_POST['shop_id']);
        $query->execute();
        
        $row = $query->fetch();
        $currentMaskPrice = $row[0];
        $currentShopAmount = $row[1];
        if ($_POST['maskAmount'] > $currentShopAmount)
            throw new Exception("Sorry, currently shop's mask amount is less than order's mask amount.");
        if ($_POST['maskPrice'] != $currentMaskPrice)
            throw new Exception("Sorry, shop's mask price has been updated.");

        $query = $connection->prepare('insert into orders (order_status, customer_id, shop_id, purchase_price, purchase_amount) 
                                       values (0, ' . $_SESSION['user_id'] . ', ' . $_POST['shop_id'] . ', ' . $_POST['maskPrice'] . ', :amount)');
        $query->execute(array('amount' => $_POST['maskAmount']));
        $currentShopAmount -= $_POST['maskAmount'];
        $query = $connection->prepare('update shops set stock_quantity = :stock_quantity where shop_id = ' . $_POST['shop_id']);
        $query->execute(array('stock_quantity' => $currentShopAmount));

        $connection->commit();
                                    
        echo <<<EOT
            <!DOCTYPE html>
            <html>
                <body>
                    <script>
                        alert("Create Order Success!");
                        window.location.replace("myOrder.php");
                    </script>
                </body>
            </html>
        EOT;
        exit();
    }
    else if (isset($_POST['cancelOrder']) || isset($_POST['finishOrder'])) {
        $orderIds = array();
        if (isset($_POST['orderIds']))
            $orderIds = $_POST['orderIds'];
        else if (isset($_POST['order_id'])) 
            $orderIds[0] = $_POST['order_id'];
        else
            throw new Exception('No order_id!');
        
        $statusToUpdate = (isset($_POST['cancelOrder']) ? 2 : 1);
        $hasFail = false;
        $failMessage = 'Failed order(s) : \n';
        foreach($orderIds as $order_id) {
            $connection->beginTransaction();
            
            $query = $connection->prepare('select order_status, shop_id, purchase_amount from orders where order_id = ' . $order_id);
            $query->execute();
            $row = $query->fetch();
            $status = $row[0];
            $shop_id = $row[1];
            $returnAmount = $row[2];

            if ($status != 0) {
                $condition = ($status == 1 ? 'finished' : 'cancelled');
                $failMessage .= 'Order id ' . $order_id . ' has been ' . $condition . '.\n';
                $hasFail = true;
                $connection->commit();
                continue;
            }
    
            $query = $connection->prepare('update orders set order_status = ' . $statusToUpdate . ', administer_id = ' . $_SESSION['user_id'] . ' where order_id = ' . $order_id);
            $query->execute();

            if ($statusToUpdate == 2) {
                $query = $connection->prepare('select stock_quantity from shops where shop_id = ' . $shop_id);
                $query->execute();
                $currentStockQuantity = $query->fetch()[0];
                $currentStockQuantity += $returnAmount;
                $query = $connection->prepare('update shops set stock_quantity = ' . $currentStockQuantity . ' where shop_id = ' . $shop_id);
                $query->execute();
            }
    
            $connection->commit();
        }
        
        if ($hasFail)
            throw new Exception($failMessage);
        
        if (isset($_POST['actionPage']))
            $dest_page = ($_POST['actionPage'] == 0 ? 'myOrder.php' : 'shopOrder.php');
        else
            $dest_page = 'myOrder.php';
        $msg = ($statusToUpdate == 1 ? 'Finish' : 'Cancel');
        $msg .= ' Order(s) Success!';
        echo <<<EOT
            <!DOCTYPE html>
            <html>
                <body>
                    <script>
                        alert("$msg");
                        window.location.replace("$dest_page");
                    </script>
                </body>
            </html>
        EOT;
        exit();
    }
    else 
        throw new Exception('No action is executed!');
}
catch(exception $e) {
    if ($connection->inTransaction())
        $connection->rollBack();

    $msg=$e->getMessage();
    echo <<<EOT
        <!DOCTYPE html>
        <html>
            <body>
                <script>
                    alert("$msg");
                    window.location.replace("userPage.php");
                </script>
            </body>
        </html>
    EOT;
}

?>