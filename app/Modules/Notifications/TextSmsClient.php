<?php
declare(strict_types=1);
namespace App\Modules\Notifications;
final class TextSmsClient {
    public function send(string $mobile,string $message): array {
        $enabled = filter_var($_ENV['TEXTSMS_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOL);
        if(!$enabled) return ['sent'=>false,'skipped'=>true,'code'=>'disabled'];
        $apiKey=trim((string)($_ENV['TEXTSMS_API_KEY']??'')); $partner=trim((string)($_ENV['TEXTSMS_PARTNER_ID']??''));
        $sender=trim((string)($_ENV['TEXTSMS_SENDER_ID']??($_ENV['APP_NAME']??'Dukame')));
        $url=trim((string)($_ENV['TEXTSMS_API_URL']??'https://sms.textsms.co.ke/api/services/sendsms/'));
        if($apiKey===''||$partner===''||$sender==='') throw new \RuntimeException('TextSMS is not configured.');
        $payload=json_encode(['apikey'=>$apiKey,'partnerID'=>$partner,'message'=>$message,'shortcode'=>$sender,'mobile'=>$mobile,'pass_type'=>'plain'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>$payload,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15]);
        $raw=curl_exec($ch); $errno=curl_errno($ch); $error=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if($raw===false||$errno) throw new \RuntimeException('TextSMS request failed: '.($error?:'connection error'));
        $json=json_decode($raw,true);
        if(!is_array($json)) throw new \RuntimeException('TextSMS returned an invalid response.');
        $response=is_array($json['responses']??null)?($json['responses'][0]??[]):$json;
        $code=(string)($response['respose-code']??$response['response-code']??$response['response_code']??'');
        $sent=$http>=200&&$http<300&&in_array($code,['200','201'],true);
        return ['sent'=>$sent,'skipped'=>false,'http_code'=>$http,'code'=>$code,'message_id'=>$response['messageid']??null,'description'=>$response['response-description']??null,'raw'=>$raw];
    }
}
