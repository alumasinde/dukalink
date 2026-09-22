<?php
declare(strict_types=1);
namespace App\Modules\Payments;
use App\Modules\Shops\ShopRepository;
use PDO;
use function App\Support\normalize_phone;
final class MpesaService {
    public function __construct(private PDO $db) {}
    public function configured(int $shopId): bool {
        $c=(new ShopRepository($this->db))->mpesaCredentials($shopId);
        return !empty($c['shortcode']) && !empty($c['consumer_key']) && !empty($c['consumer_secret']) && !empty($c['passkey']);
    }
    public function initiate(int $shopId,int $orderId,float $amount,string $phone,string $currency,string $orderNumber): array {
        if($currency!=='KES') throw new \RuntimeException('M-Pesa checkout is currently available for KES stores only.');
        $repo=new ShopRepository($this->db); $c=$repo->mpesaCredentials($shopId);
        foreach(['shortcode','consumer_key','consumer_secret','passkey'] as $key) if(empty($c[$key])) throw new \RuntimeException('This store has not completed M-Pesa setup.');
        $phone=normalize_phone($phone); if(!preg_match('/^254\d{9}$/',$phone)) throw new \InvalidArgumentException('Enter a valid Kenyan phone number for M-Pesa.');
        $base=$c['environment']==='production'?'https://api.safaricom.co.ke':'https://sandbox.safaricom.co.ke';
        $token=$this->requestJson($base.'/oauth/v1/generate?grant_type=client_credentials','Basic '.base64_encode($c['consumer_key'].':'.$c['consumer_secret']),[]);
        if(empty($token['access_token'])) throw new \RuntimeException('Could not authenticate with M-Pesa.');
        $timestamp=date('YmdHis'); $password=base64_encode($c['shortcode'].$c['passkey'].$timestamp);
        $callback=rtrim((string)($_ENV['APP_URL']??''),'/').'/api/v1/payments/mpesa/callback';
        if($callback==='/api/v1/payments/mpesa/callback') throw new \RuntimeException('APP_URL must be configured before M-Pesa payments can be used.');
        $body=['BusinessShortCode'=>$c['shortcode'],'Password'=>$password,'Timestamp'=>$timestamp,'TransactionType'=>'CustomerPayBillOnline','Amount'=>(int)round($amount),'PartyA'=>$phone,'PartyB'=>$c['shortcode'],'PhoneNumber'=>$phone,'CallBackURL'=>$callback,'AccountReference'=>$orderNumber,'TransactionDesc'=>'Dukame order '.$orderNumber];
        $response=$this->requestJson($base.'/mpesa/stkpush/v1/processrequest','Bearer '.$token['access_token'],$body);
        if(empty($response['CheckoutRequestID'])) throw new \RuntimeException((string)($response['errorMessage']??$response['ResponseDescription']??'M-Pesa STK request failed.'));
        $s=$this->db->prepare('INSERT INTO payments (shop_id,order_id,provider,amount,currency,phone,status,merchant_request_id,checkout_request_id,result_description) VALUES (:shop_id,:order_id,"mpesa",:amount,:currency,:phone,"pending",:merchant_request_id,:checkout_request_id,:description)');
        $s->execute(['shop_id'=>$shopId,'order_id'=>$orderId,'amount'=>$amount,'currency'=>$currency,'phone'=>$phone,'merchant_request_id'=>$response['MerchantRequestID']??null,'checkout_request_id'=>$response['CheckoutRequestID'],'description'=>$response['ResponseDescription']??null]);
        return $response;
    }
    public function handleCallback(array $payload): void {
        $cb=$payload['Body']['stkCallback']??null; if(!is_array($cb)) throw new \InvalidArgumentException('Invalid M-Pesa callback.');
        $checkout=(string)($cb['CheckoutRequestID']??''); if($checkout==='') return;
        $resultCode=(string)($cb['ResultCode']??''); $description=(string)($cb['ResultDesc']??'');
        $status=$resultCode==='0'?'paid':'failed'; $receipt=null;
        foreach(($cb['CallbackMetadata']['Item']??[]) as $item) if(($item['Name']??'')==='MpesaReceiptNumber') $receipt=(string)($item['Value']??'');
        $s=$this->db->prepare('SELECT * FROM payments WHERE checkout_request_id=:checkout LIMIT 1'); $s->execute(['checkout'=>$checkout]); $payment=$s->fetch(); if(!$payment)return;
        $this->db->beginTransaction(); try {
            $u=$this->db->prepare('UPDATE payments SET status=:status,mpesa_receipt=:receipt,result_code=:code,result_description=:description,raw_callback=:raw WHERE id=:id');
            $u->execute(['status'=>$status,'receipt'=>$receipt,'code'=>$resultCode,'description'=>$description,'raw'=>json_encode($payload,JSON_UNESCAPED_UNICODE),'id'=>$payment['id']]);
            $o=$this->db->prepare('UPDATE orders SET payment_status=:payment_status WHERE id=:id'); $o->execute(['payment_status'=>$status==='paid'?'paid':'failed','id'=>$payment['order_id']]);
            $this->db->commit();
        } catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    private function requestJson(string $url,string $auth,array $body): array {
        $ch=curl_init($url); $headers=['Authorization: '.$auth,'Accept: application/json']; $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>7,CURLOPT_TIMEOUT=>20];
        if($body){$headers[]='Content-Type: application/json';$opts[CURLOPT_HTTPHEADER]=$headers;$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=json_encode($body);}
        curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($raw===false)throw new \RuntimeException('M-Pesa request failed: '.$err);$json=json_decode($raw,true);if(!is_array($json))throw new \RuntimeException('M-Pesa returned an invalid response.');if($code<200||$code>=300)throw new \RuntimeException((string)($json['errorMessage']??$json['ResponseDescription']??'M-Pesa request failed.'));return $json;
    }
}
