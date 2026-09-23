<?php
declare(strict_types=1);
namespace App\Api\V1\Controllers;
use App\Api\V1\Support\JsonResponse;
use App\Api\V1\Support\Request;
use App\Database\Database;
use App\Modules\Subscriptions\SubscriptionMpesaService;
use App\Modules\Subscriptions\EntitlementService;
use App\Support\Csrf;
use App\Support\Session;
use App\Support\RateLimiter;
final class SubscriptionController {
 public static function initiate(): never {
  if(!Csrf::verify($_POST['_csrf'] ?? null)) JsonResponse::error('Invalid form session.',419,'csrf_failed');
  $userId=(int)Session::get('user_id'); $shopId=(int)Session::get('shop_id'); if($userId<=0||$shopId<=0) JsonResponse::error('Authentication required.',401,'unauthenticated');
  if (RateLimiter::tooMany('subscription-stk:' . $shopId, max(3, (int)($_ENV['SUBSCRIPTION_STK_RATE_LIMIT_MAX'] ?? 5)), max(60, (int)($_ENV['SUBSCRIPTION_STK_RATE_LIMIT_WINDOW'] ?? 900)))) JsonResponse::error('Too many subscription payment attempts. Please try again later.', 429, 'rate_limited');
  $planId=(int)($_POST['plan_id']??0); $interval=(string)($_POST['billing_interval']??'monthly'); $phone=trim((string)($_POST['phone']??'')); if($planId<1) JsonResponse::error('Invalid plan.',422,'invalid_plan');
  try { $result=(new SubscriptionMpesaService(Database::connection()))->initiate($shopId,$userId,$planId,$interval,$phone); JsonResponse::success($result,201); } catch(\Throwable $e){ JsonResponse::error($e->getMessage(),422,'subscription_payment_failed'); }
 }
 public static function status(): never {
  $shopId=(int)Session::get('shop_id'); $paymentId=(int)($_GET['payment_id']??0); if($paymentId<1) JsonResponse::error('Invalid payment.',422,'invalid_payment'); if($shopId<=0) JsonResponse::error('Authentication required.',401,'unauthenticated');
  $payment=(new SubscriptionMpesaService(Database::connection()))->statusForMerchant($paymentId,$shopId); if(!$payment) JsonResponse::error('Payment not found.',404,'not_found');
  $subscription=(new EntitlementService(Database::connection()))->subscription($shopId); JsonResponse::success(['payment'=>$payment,'subscription'=>$subscription]);
 }
 public static function callback(): never {
  $raw=file_get_contents('php://input'); $payload=json_decode($raw?:'',true); try{(new SubscriptionMpesaService(Database::connection()))->handleCallback(is_array($payload)?$payload:[]);}catch(\Throwable $e){error_log('Subscription M-Pesa callback: '.$e->getMessage());} header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Accepted']); exit;
 }
}
