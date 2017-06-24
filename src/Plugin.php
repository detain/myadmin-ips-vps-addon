<?php

namespace Detain\MyAdminVpsIps;

use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public static $name = 'Ips Licensing VPS Addon';
	public static $description = 'Allows selling of Ips Server and VPS License Types.  More info at https://www.netenberg.com/ips.php';
	public static $help = 'It provides more than one million end users the ability to quickly install dozens of the leading open source content management systems into their web space.  	Must have a pre-existing cPanel license with cPanelDirect to purchase a ips license. Allow 10 minutes for activation.';
	public static $module = 'vps';
	public static $type = 'addon';


	public function __construct() {
	}

	public static function getHooks() {
		return [
			self::$module.'.load_addons' => [__CLASS__, 'getAddon'],
			self::$module.'.settings' => [__CLASS__, 'getSettings'],
		];
	}

	public static function getAddon(GenericEvent $event) {
		$service = $event->getSubject();
		function_requirements('class.Addon');
		$addon = new \Addon();
		$addon->setModule(self::$module)
			->set_text('Additional IP')
			->set_text_match('Additional IP (.*)')
			->set_cost(VPS_IP_COST)
			->set_require_ip(TRUE)
			->set_enable([__CLASS__, 'doEnable'])
			->set_disable([__CLASS__, 'doDisable'])
			->register();
		$service->addAddon($addon);
	}

	public static function doEnable(\Service_Order $serviceOrder, $repeatInvoiceId, $regexMatch = FALSE) {
		$serviceInfo = $serviceOrder->getServiceInfo();
		$settings = get_module_settings(self::$module);
		$db = get_module_db(self::$module);
		if ($regexMatch === FALSE) {
			$ip = vps_get_next_ip($serviceInfo[$settings['PREFIX'].'_server']);
			myadmin_log(self::$module, 'info', 'Trying To Give '.$settings['TITLE'].' '.$serviceInfo[$settings['PREFIX'].'_id'].' Repeat Invoice '.$repeatInvoiceId.' IP ' . ($ip === FALSE ? '<ip allocation failed>' : $ip), __LINE__, __FILE__);
			if ($ip) {
				$GLOBALS['tf']->history->add(self::$module . 'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'add_ip', $ip, $serviceInfo[$settings['PREFIX'].'_custid']);
				$description = 'Additional IP ' . $ip . ' for ' . $settings['TBLNAME'] . ' ' . $serviceInfo[$settings['PREFIX'].'_id'];
				$rdescription = '(Repeat Invoice: ' . $repeatInvoiceId . ') ' . $description;
				$db->query("update {$settings['PREFIX']}_ips set ips_main=0,ips_used=1,ips_{$settings['PREFIX']}={$serviceInfo[$settings['PREFIX'].'_id']} where ips_ip='{$ip}'", __LINE__, __FILE__);
				$db->query("update invoices set invoices_description='{$rdescription}' where invoices_type=1 and invoices_extra='{$repeatInvoiceId}'", __LINE__, __FILE__);
				$db->query("update repeat_invoices set repeat_invoices_description='{$description}' where repeat_invoices_id='{$repeatInvoiceId}'", __LINE__, __FILE__);
			} else {
				$db->query('SELECT * FROM ' . $settings['PREFIX'].'_masters WHERE ' . $settings['PREFIX'].'_id=' . $serviceInfo[$settings['PREFIX'].'_server'], __LINE__, __FILE__);
				$db->next_record(MYSQL_ASSOC);
				$headers = '';
				$headers .= 'MIME-Version: 1.0' . EMAIL_NEWLINE;
				$headers .= 'Content-type: text/html; charset=UTF-8' . EMAIL_NEWLINE;
				$headers .= 'From: ' . TITLE . ' <' . EMAIL_FROM . '>' . EMAIL_NEWLINE;
				$subject = '0 Free IPs On ' . $settings['TBLNAME'] . ' Server ' . $db->Record[$settings['PREFIX'].'_name'];
				admin_mail($subject, $settings['TBLNAME'] . " {$serviceInfo[$settings['PREFIX'].'_id']} Has Pending IPS<br>\n" . $subject, $headers, FALSE, 'admin_email_vps_no_ips.tpl');
			}
		} else {
			$ip = $regexMatch;
			$GLOBALS['tf']->history->add(self::$module . 'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'ensure_addon_ip', $ip, $serviceInfo[$settings['PREFIX'].'_custid']);
			$db->query("update {$settings['PREFIX']}_ips set ips_main=0,ips_used=1,ips_{$settings['PREFIX']}={$serviceInfo[$settings['PREFIX'].'_id']} where ips_ip='{$ip}'", __LINE__, __FILE__);
		}
	}

	public static function doDisable(\Service_Order $serviceOrder) {
		$serviceInfo = $serviceOrder->getServiceInfo();
		$settings = get_module_settings(self::$module);
	}

	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_text_setting(self::$module, 'Addon Costs', 'vps_ip_cost', 'VPS Additional IP Cost:', 'This is the cost for purchasing an additional IP on top of a VPS.', $settings->get_setting('VPS_IP_COST'));
		$settings->add_text_setting(self::$module, 'Slice Amounts', 'vps_max_ips', 'Max Addon IP Addresses:', 'Maximum amount of additional IPs you can add to your VPS', $settings->get_setting('VPS_MAX_IPS'));
	}
}
