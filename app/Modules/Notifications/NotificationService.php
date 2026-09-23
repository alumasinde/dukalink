<?php
declare(strict_types=1);
namespace App\Modules\Notifications;
use App\Database\Database;
use App\Modules\Orders\OrderRepository;
use PDO;
use function App\Support\base_url;
use function App\Support\normalize_phone;
final class NotificationService {
    public function __construct(private PDO $db) {}
    public function orderEvent(array $order,string $event): void {
        $shopId=(int)$order['shop_id']; $orderId=(int)$order['id'];
        $template=(new NotificationTemplateRepository($this->db))->find($shopId,$event);
        if(!$template || empty($template['enabled'])) return;
        $recipient=normalize_phone((string)$order['customer_phone']); if($recipient==='') return;
        $message=$this->render((string)$template['template'],$order);
        $insert=$this->db->prepare('INSERT IGNORE INTO notification_logs (shop_id,order_id,event_key,channel,recipient,message,status) VALUES (:shop_id,:order_id,:event,:channel,:recipient,:message,"pending")');
        $insert->execute(['shop_id'=>$shopId,'order_id'=>$orderId,'event'=>$event,'channel'=>'sms','recipient'=>$recipient,'message'=>$message]);
        if($insert->rowCount()===0) return;
        $logId=(int)$this->db->lastInsertId();
        try {
            $result=(new TextSmsClient())->send($recipient,$message);
            if(!empty($result['skipped'])) { $this->mark($logId,'failed',null,'SMS disabled by platform configuration.'); return; }
            if(!empty($result['sent'])) $this->mark($logId,'sent',(string)($result['message_id']??''),null,(string)($result['code']??''),json_encode($result));
            else $this->mark($logId,'failed',(string)($result['message_id']??''),(string)($result['description']??'TextSMS rejected the message.'),(string)($result['code']??''),json_encode($result));
        } catch(\Throwable $e) { $this->mark($logId,'failed',null,$e->getMessage(),null,null); }
    }
    public function retryFailed(int $limit=25): int {
        $limit=max(1,min(100,$limit));
        $sql='SELECT * FROM notification_logs WHERE status="failed" AND channel="sms" ORDER BY id ASC LIMIT '.$limit;
        $rows=$this->db->query($sql)->fetchAll();
        $count=0;
        foreach($rows as $row){
            try {
                $result=(new TextSmsClient())->send((string)$row['recipient'],(string)$row['message']);
                if(!empty($result['sent'])){
                    $this->mark((int)$row['id'],'sent',(string)($result['message_id']??''),null,(string)($result['code']??''),json_encode($result));
                    $count++;
                } else {
                    $this->mark((int)$row['id'],'failed',(string)($result['message_id']??''),(string)($result['description']??'TextSMS rejected the message.'),(string)($result['code']??''),json_encode($result));
                }
            } catch(\Throwable $e){ $this->mark((int)$row['id'],'failed',null,$e->getMessage(),null,null); }
        }
        return $count;
    }

    private function mark(int $id,string $status,?string $messageId=null,?string $error=null,?string $code=null,?string $response=null):void {
        $s=$this->db->prepare('UPDATE notification_logs SET status=:status,provider_message_id=:message_id,error_message=:error_message,provider_code=:provider_code,provider_response=:provider_response,sent_at=CASE WHEN :status2="sent" THEN NOW() ELSE sent_at END WHERE id=:id');
        $s->execute(['status'=>$status,'status2'=>$status,'message_id'=>$messageId?:null,'error_message'=>$error?:null,'provider_code'=>$code?:null,'provider_response'=>$response?:null,'id'=>$id]);
    }
    private function render(string $template,array $order):string {
        $first=(string)($order['customer_first_name']??'');
        $last=(string)($order['customer_last_name']??'');
        $shop=(string)($order['shop_name']??'');
        $vars=[
            '{App Name}'=>($_ENV['APP_NAME']??'Dukame'),
            '{Store Name}'=>$shop,
            '{Customer First Name}'=>$first,
            '{Customer Last Name}'=>$last,
            '{Customer Name}'=>trim($first.' '.$last) ?: (string)($order['customer_name']??''),
            '{Order Number}'=>(string)($order['order_number']??''),
            '{Order Total}'=>number_format((float)($order['total']??0),2),
            '{Currency}'=>(string)($order['currency']??'KES'),
            '{Track URL}'=>base_url('track'),
            '{Store URL}'=>base_url((string)($order['shop_slug']??'')),
        ];
        return trim(strtr($template,$vars));
    }
}
