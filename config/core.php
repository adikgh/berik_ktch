<? 

   require 'db.php';
   require 'fun.php';
   require 'product.php';
   require 't.php';
   // require 'smsc_api.php';
   require 'var.php';
   require 'auth.php'; // пароль, cookie, WhatsApp коды

   class core {
      public static $user_ph = false;
      public static $user_ps = false;
      public static $user_data = array();

      public function __construct() {
         new db; new t; new fun; new product;
         $time = 3600 * 24 * 365;
         // ini_set('session.gc_maxlifetime', $time);
         // ini_set('session.cookie_lifetime', $time);
         session_set_cookie_params($time);
         session_start();
         date_default_timezone_set('Asia/Aqtau');
         $this->authorize();
      }

      private function authorize() {
         $user_ph = false;
         $user_ps = false;

         if (isset($_COOKIE['uph']) && isset($_COOKIE['ups'])) {
            $user_ph = substr(preg_replace('/\D/', '', (string)$_COOKIE['uph']), -10);
            $user_ps = $_COOKIE['ups'];
         }
         if ($user_ph && $user_ps) {
            $user = db::query("SELECT * FROM user WHERE `phone` = '$user_ph'");
            if (mysqli_num_rows($user)) {
               $user_data = mysqli_fetch_assoc($user);
               if ($user_data['right'] && auth_check($user_data, $user_ps)) {
                  self::$user_ph = $user_ph;
                  self::$user_data = $user_data;
               } else $this->user_unset();
            } else $this->user_unset();
         }
      }
   
      public function user_unset() {
         self::$user_ph = false;
         self::$user_data = array();
         setcookie('uph', '', time(), '/');
         setcookie('ups', '', time(), '/');
      }

   }


   // data
   $core = new core;
   $user = core::$user_data;
   $user_id = @$user['id'];
   $user_right = fun::user_staffw($user_id);



   // setting
   $site = mysqli_fetch_array(db::query("select * from `site` where id = 1"));
   $ver = 3.004;

   // 
   $site_set = [
      'menu' => true,
      'search' => true,
      // 'swiper' => false,
      // 'plyr' => false,
      // 'aos' => false,
   ];
   $scss = ['norm', 'main'];
   $sjs = ['norm', 'main'];
   $css = [];
   $js = [];
   $code = rand(1000, 9999);
   $token = (string)(@(is_file(__DIR__.'/secret.php') ? include __DIR__.'/secret.php' : [])['tg_token'] ?? ''); // config/secret.php



   // lang
   // Тіл: kz (стандарт), en, uz, ky, ru. ?lang= басылса cookie-ге жазылып, сол бетке параметрсіз оралады
   $langs_short = ['kz' => 'ҚАЗ', 'en' => 'ENG', 'uz' => 'UZB', 'ky' => 'KGZ', 'ru' => 'РУС']; // кассадағыдай
   if (isset($_GET['lang']) && isset($langs_short[$_GET['lang']])) {
      $_SESSION['lang'] = $_GET['lang'];
      setcookie('lang', $_GET['lang'], time() + 3600 * 24 * 365, '/');
      $q = $_GET; unset($q['lang']);
      header('location: '.strtok($_SERVER['REQUEST_URI'], '?').($q ? '?'.http_build_query($q) : ''));
      exit();
   }
   $lang = 'kz';
   if (isset($_COOKIE['lang']) && isset($langs_short[$_COOKIE['lang']])) $lang = $_COOKIE['lang'];
   elseif (isset($_SESSION['lang']) && isset($langs_short[$_SESSION['lang']])) $lang = $_SESSION['lang'];
   t::$lang = $lang;

   // company
   $company = 1;
   if (@$user_right['company_id']) $company = $user_right['company_id'];
   else {
      // компания id емес, code арқылы: ?company=n990 (базада #n990)
      if (isset($_GET['company'])) {
         $gc = '#'.preg_replace('/[^a-z0-9]/', '', strtolower((string)$_GET['company']));
         $gr = mysqli_fetch_assoc(db::query("select id from company where lower(code) = '$gc' limit 1"));
         if ($gr) $_SESSION['company'] = (int)$gr['id'];
      }
      if (isset($_SESSION['company'])) $company = $_SESSION['company'];
   }
   $company_d = mysqli_fetch_array(db::query("select * from `company` where id = '$company'"));


   // branch
   $branch = 0; if (@$user_right['branch_id']) $branch = $user_right['branch_id'];
   // if (@$user_right['positions_id'] != 4) {
   //    if (isset($_GET['branch'])) if ($_GET['branch'] == 1 || $_GET['branch'] == 2) $_SESSION['branch'] = $_GET['branch'];
   //    if (isset($_SESSION['branch'])) $branch = $_SESSION['branch'];
   // }

   // lang
   $super = @$user_right['positions_id'];
   // if (isset($_SESSION['super'])) $super = $_SESSION['super'];




   // date - time
   $date = date("Y-m-d", time());
   $time = date("H:i:s", time());
   $datetime = date('Y-m-d H:i:s', time());

   if ($time > "00:00:00" && $time < "06:00:00") {
      $start_cdate = date('Y-m-d 06:00:00', strtotime("$date -1 day"));
      $end_cdate = date('Y-m-d 06:00:00');
   } else {
      $start_cdate = date('Y-m-d 06:00:00');
      $end_cdate = date("Y-m-d 06:00:00", strtotime("$start_cdate +1 day"));
   }

   // $start_cdate = date('Y-m-d 06:00:00', strtotime("$date -1 day"));
   // $end_cdate = date("Y-m-d 06:00:00", strtotime("$start_cdate +1 day"));


   // url
   $url = $url_full = $_SERVER['REQUEST_URI'];
   $url = explode('?', $url);
   $url = $url[0];


