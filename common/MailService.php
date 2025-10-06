<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService {
    private static $instance = null;
    private $mailer;
    private $siteInfo;
    
    private function __construct() {
        global $site_info;
        $this->siteInfo = $site_info ?? ['site_title' => '未知网站'];
        
        $this->mailer = new PHPMailer(true);
        
        try {
            $this->mailer->SMTPDebug = MAIL_DEBUG;
            $this->mailer->isSMTP();
            $this->mailer->Host = MAIL_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = MAIL_USERNAME;
            $this->mailer->Password = MAIL_PASSWORD;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $this->mailer->Port = MAIL_PORT;
            $this->mailer->CharSet = 'UTF-8';
            
            $this->mailer->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        } catch (Exception $e) {
            $this->logError('初始化邮件服务失败: ' . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 发送验证码邮件
     * @param string $to 收件人邮箱
     * @param string $type 邮件类型(register/change_email/forgot_password)
     * @return array
     */
    public function sendVerificationCode($to, $type) {
        try {
            // 生成6位随机验证码
            $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // 设置验证码过期时间(5分钟)
            $expire_time = time() + VERIFY_CODE_EXPIRE;
            
            // 存储验证码到Redis
            $redis = new Redis();
            $redis->connect(REDIS_HOST, REDIS_PORT);
            if (REDIS_PASS) $redis->auth(REDIS_PASS);
            
            $key = REDIS_PREFIX . 'verify_code:' . $type . ':' . $to;
            $redis->setex($key, VERIFY_CODE_EXPIRE, json_encode([
                'code' => $code,
                'expire' => $expire_time
            ]));
            
            // 设置邮件内容
            $subject = '';
            $template = '';
            
            switch ($type) {
                case 'register':
                    $subject = htmlspecialchars($this->siteInfo['site_title']) . ' - 注册验证码';
                    $template = $this->getRegisterTemplate($code);
                    break;
                case 'change_email':
                    $subject = htmlspecialchars($this->siteInfo['site_title']) . ' - 邮箱修改验证码';
                    $template = $this->getChangeEmailTemplate($code);
                    break;
                case 'forgot_password':
                    $subject = htmlspecialchars($this->siteInfo['site_title']) . ' - 密码重置验证码';
                    $template = $this->getForgotPasswordTemplate($code);
                    break;
                default:
                    throw new Exception('未知的邮件类型');
            }
            
            // 设置收件人
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            
            // 设置邮件内容
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $template;
            $this->mailer->AltBody = "您的验证码是: $code (有效期5分钟)";
            
            $this->mailer->send();
            return ['success' => true];
        } catch (Exception $e) {
            $this->logError("发送验证码邮件失败: {$e->getMessage()} | 收件人: {$to}");
            return ['success' => false, 'message' => '邮件发送失败，请稍后再试'];
        }
    }
    
    /**
     * 发送通知邮件
     * @param string $to 收件人邮箱
     * @param string $subject 邮件话题
     * @param string $content 邮件内容
     * @return array
     */
    public function sendNotification($to, $subject, $content) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $this->getNotificationTemplate($content);
            $this->mailer->AltBody = strip_tags($content);
            
            $this->mailer->send();
            return ['success' => true];
        } catch (Exception $e) {
            $this->logError("发送通知邮件失败: {$e->getMessage()} | 收件人: {$to}");
            return ['success' => false, 'message' => '邮件发送失败'];
        }
    }
    
    private function getRegisterTemplate($code) {
        $siteTitle = htmlspecialchars($this->siteInfo['site_title']);
        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #4a86e8;'>欢迎注册{$siteTitle}</h2>
                <p>感谢您注册{$siteTitle}，以下是您的验证码：</p>
                <div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; font-size: 24px; font-weight: bold; text-align: center; margin: 20px 0; color: #4a86e8;'>
                    {$code}
                </div>
                <p>验证码将在5分钟后失效，请尽快完成注册。</p>
                <p>如果您没有进行此操作，请忽略此邮件。</p>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 12px; color: #999;'>此邮件由系统自动发送，请勿直接回复。</p>
            </div>
        ";
    }
    
    private function getChangeEmailTemplate($code) {
        $siteTitle = htmlspecialchars($this->siteInfo['site_title']);
        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #4a86e8;'>邮箱修改验证码</h2>
                <p>您正在尝试修改{$siteTitle}的绑定邮箱，以下是您的验证码：</p>
                <div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; font-size: 24px; font-weight: bold; text-align: center; margin: 20px 0; color: #4a86e8;'>
                    {$code}
                </div>
                <p>验证码将在5分钟后失效，请尽快完成操作。</p>
                <p>如果您没有进行此操作，请立即修改您的账户密码并联系管理员。</p>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 12px; color: #999;'>此邮件由系统自动发送，请勿直接回复。</p>
            </div>
        ";
    }
    
    private function getForgotPasswordTemplate($code) {
        $siteTitle = htmlspecialchars($this->siteInfo['site_title']);
        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #4a86e8;'>密码重置验证码</h2>
                <p>您正在尝试重置{$siteTitle}的账户密码，以下是您的验证码：</p>
                <div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; font-size: 24px; font-weight: bold; text-align: center; margin: 20px 0; color: #4a86e8;'>
                    {$code}
                </div>
                <p>验证码将在5分钟后失效，请尽快完成操作。</p>
                <p>如果您没有进行此操作，请立即修改您的账户密码并联系管理员。</p>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 12px; color: #999;'>此邮件由系统自动发送，请勿直接回复。</p>
            </div>
        ";
    }
    
    private function getNotificationTemplate($content) {
        $siteTitle = htmlspecialchars($this->siteInfo['site_title']);
        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #4a86e8;'>{$siteTitle}通知</h2>
                <div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    {$content}
                </div>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 12px; color: #999;'>此邮件由系统自动发送，请勿直接回复。</p>
            </div>
        ";
    }
    
    private function logError($message) {
        // 确保日志目录存在
        $logDir = __DIR__ . '/../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/mail.log';
        $timestamp = date('Y-m-d H:i:s');
        $message = "[{$timestamp}] {$message}\n";
        
        file_put_contents($logFile, $message, FILE_APPEND);
    }
}
    