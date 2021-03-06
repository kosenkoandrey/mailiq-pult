<?
class Pages {

    function __construct($conf) {
        foreach ($conf['routes'] as $route) APP::Module('Routing')->Add($route[0], $route[1], $route[2]);
    }

    public function ProductGarderob100Sale() {
        $cookie_tunnel_subscribtion_id = isset($_COOKIE['tunnel_subscribtion_id']) ? $_COOKIE['tunnel_subscribtion_id'] : 0;
        $tunnel_subscribtion_id = isset(APP::Module('Routing')->get['token']) ? APP::Module('Crypt')->Decode(APP::Module('Routing')->get['token']) : $cookie_tunnel_subscribtion_id;

        if (APP::Module('DB')->Select(
            APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_COLUMN], 
            ['COUNT(id)'], 'tunnels_users',
            [['id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT]]
        )) {
            setcookie('tunnel_subscribtion_id', $tunnel_subscribtion_id, time() + 31556926, '/', '.glamurnenko.ru');
        } else {
            APP::Render('pages/products/garderob100/0');
            exit;
        }
        
        if (APP::Module('DB')->Select(
            APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_COLUMN], 
            ['COUNT(id)'], 'tunnels_users',
            [
                ['id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT],
                ['tunnel_id', '=', 57, PDO::PARAM_INT]
            ]
        )) {
            $tunnel_subscribtion = APP::Module('DB')->Select(
                APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_ASSOC], 
                ['id', 'user_id', 'state'], 'tunnels_users',
                [['id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT]]
            );
        } else {
            APP::Render('pages/products/garderob100/0');
            exit;
        }
        
        if ($tunnel_subscribtion['state'] != 'active') {
            APP::Render('pages/products/garderob100/5', 'include', $tunnel_subscribtion);
            exit;
        }
        
        $tunnel_subscription_labels = [];
        
        foreach (APP::Module('DB')->Select(
            APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetchAll', PDO::FETCH_ASSOC], 
            ['label_id', 'token'], 'tunnels_tags',
            [['user_tunnel_id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT]]
        ) as $label) {
            $tunnel_subscription_labels[$label['label_id']][] = $label['token'];
        }
        
        // Получил 6/7/8 письмо
        if (
            (array_search('34', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('35', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('36', $tunnel_subscription_labels['sendmail']) !== false)
        ) {
            APP::Render('pages/products/garderob100/4', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил 5 письмо
        if (array_search('33', $tunnel_subscription_labels['sendmail']) !== false) {
            APP::Render('pages/products/garderob100/3', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил 4 письмо
        if (array_search('32', $tunnel_subscription_labels['sendmail']) !== false) {
            APP::Render('pages/products/garderob100/2', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил 1/2/3 письмо
        if (
            (array_search('29', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('30', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('31', $tunnel_subscription_labels['sendmail']) !== false)
        ) {
            APP::Render('pages/products/garderob100/1', 'include', $tunnel_subscribtion);
            exit;
        }
    }
    
    public function ProductGarderob100Form() {
        APP::Render('pages/products/garderob100/form');
    }
    
    
    public function ProductBigColorSale(){
        // Получение ID подписки на туннель
        $cookie_tunnel_subscribtion_id = isset($_COOKIE['tunnel_subscribtion_id']) ? $_COOKIE['tunnel_subscribtion_id'] : 0;
        $tunnel_subscribtion_id = isset(APP::Module('Routing')->get['token']) ? APP::Module('Crypt')->Decode(APP::Module('Routing')->get['token']) : $cookie_tunnel_subscribtion_id;
        
        if (APP::Module('DB')->Select(
            APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_COLUMN], 
            ['COUNT(id)'], 'tunnels_users',
            [['id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT]]
        )) {
            setcookie('tunnel_subscribtion_id', $tunnel_subscribtion_id, time() + 31556926, '/', '.glamurnenko.ru');
        } else {
            APP::Render('pages/products/bigcolor/6');
            exit;
        }
        
        // Проверка ID подписки на туннель и получение информации о подписке
        if (APP::Module('DB')->Select(
            APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_COLUMN], 
            ['COUNT(id)'], 'tunnels_users',
            [
                ['id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT],
                ['tunnel_id', '=', 60, PDO::PARAM_INT]
            ]
        )) {
            $tunnel_subscribtion = APP::Module('DB')->Select(
                APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_ASSOC], 
                ['id', 'user_id', 'state'], 'tunnels_users',
                [['id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT]]
            );
        } else {
            APP::Render('pages/products/bigcolor/6');
            exit;
        }

        // Если туннель не активный, то 5 вариант      
        if ($tunnel_subscribtion['state'] != 'active') {
            APP::Render('pages/products/bigcolor/5', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получение массива меток
        $tunnel_subscription_labels = [];
        
        foreach (APP::Module('DB')->Select(
            APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetchAll', PDO::FETCH_ASSOC], 
            ['label_id', 'token'], 'tunnels_tags',
            [['user_tunnel_id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT]]
        ) as $label) {
            $tunnel_subscription_labels[$label['label_id']][] = $label['token'];
        }

        // Логика отображения вариантов страниц ////////////////////////////////////////

        // Получил (3 сет) 3 письмо
        if (array_search('55', $tunnel_subscription_labels['sendmail']) !== false) {
            APP::Render('pages/products/bigcolor/6', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил (3 сет) 1/2 письмо
        if (
            (array_search('53', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('54', $tunnel_subscription_labels['sendmail']) !== false)
        ) {
            $label = APP::Module('DB')->Select(
                APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_ASSOC], 
                ['label_id', 'token', 'UNIX_TIMESTAMP(cr_date) AS cr_date'], 'tunnels_tags',
                [
                    ['user_tunnel_id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT],
                    ['label_id', '=', 'sendmail', PDO::PARAM_STR],
                    ['token', '=', '53', PDO::PARAM_STR]
                ]
            );

            // Дата окончания таймера
            $timer_stop = strtotime('+84 hours', $label['cr_date']);

            // Проверка на окончание таймера
            if ($timer_stop < time()) {
                // Если закончился таймер
                APP::Render('pages/products/bigcolor/6', 'include', $tunnel_subscribtion);
            } else {
                // Если не закончился таймер 
                APP::Render('pages/products/bigcolor/5', 'include', $tunnel_subscribtion);
            }
            exit;
        }

        ////////////////////////////////////////////////////////////////////////////////

        // Получил (2 сет) 5/6 письмо
        if (
            (array_search('51', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('52', $tunnel_subscription_labels['sendmail']) !== false)
        ) {
            APP::Render('pages/products/bigcolor/6', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил (2 сет) 3/4 письмо
        if (
            (array_search('49', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('50', $tunnel_subscription_labels['sendmail']) !== false)
        ) {
            $label = APP::Module('DB')->Select(
                APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_ASSOC], 
                ['label_id', 'token', 'UNIX_TIMESTAMP(cr_date) AS cr_date'], 'tunnels_tags',
                [
                    ['user_tunnel_id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT],
                    ['label_id', '=', 'sendmail', PDO::PARAM_STR],
                    ['token', '=', '47', PDO::PARAM_STR]
                ]
            );

            // Дата окончания таймера
            $timer_stop = strtotime('+84 hours', $label['cr_date']);

            // Проверка на окончание таймера
            if ($timer_stop < time()) {
                // Если закончился таймер
                APP::Render('pages/products/bigcolor/6', 'include', $tunnel_subscribtion);
            } else {
                // Если не закончился таймер
                APP::Render('pages/products/bigcolor/5', 'include', $tunnel_subscribtion);
            }
            exit;
        }

        // Получил (2 сет) 1/2 письмо
        if (
            (array_search('47', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('48', $tunnel_subscription_labels['sendmail']) !== false)
        ) {
            APP::Render('pages/products/bigcolor/4', 'include', $tunnel_subscribtion);
            exit;
        }

        ////////////////////////////////////////////////////////////////////////////////

        // Получил (1 сет) 7 письмо
        if (array_search('46', $tunnel_subscription_labels['sendmail']) !== false) {
            APP::Render('pages/products/bigcolor/3', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил (1 сет) 6 письмо
        if (array_search('45', $tunnel_subscription_labels['sendmail']) !== false) {
            APP::Render('pages/products/bigcolor/2', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил (1 сет) 5 письмо
        if (array_search('44', $tunnel_subscription_labels['sendmail']) !== false) {
            APP::Render('pages/products/bigcolor/1', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил (1 сет) 1/2/3/4 письмо
        if (
            (array_search('40', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('41', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('42', $tunnel_subscription_labels['sendmail']) !== false) ||  
            (array_search('43', $tunnel_subscription_labels['sendmail']) !== false)
        ) {
            APP::Render('pages/products/bigcolor/6', 'include', $tunnel_subscribtion);
            exit;
        }
    }
    
    public function ProductBigColorForm() {
        APP::Render('pages/products/bigcolor/form');
    }
    
    public function ProductBigColorActivation() {
        APP::Render('pages/products/bigcolor/activate');
    }
    
    
    public function Product101OfficeSale() {
        $cookie_tunnel_subscribtion_id = isset($_COOKIE['tunnel_subscribtion_id']) ? $_COOKIE['tunnel_subscribtion_id'] : 0;
        $tunnel_subscribtion_id = isset(APP::Module('Routing')->get['token']) ? APP::Module('Crypt')->Decode(APP::Module('Routing')->get['token']) : $cookie_tunnel_subscribtion_id;

        if (APP::Module('DB')->Select(
            APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_COLUMN], 
            ['COUNT(id)'], 'tunnels_users',
            [['id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT]]
        )) {
            setcookie('tunnel_subscribtion_id', $tunnel_subscribtion_id, time() + 31556926, '/', '.glamurnenko.ru');
        } else {
            APP::Render('pages/products/101office/5');
            exit;
        }
        
        // Проверка ID подписки на туннель и получение информации о подписке
        if (APP::Module('DB')->Select(
            APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_COLUMN], 
            ['COUNT(id)'], 'tunnels_users',
            [
                ['id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT],
                ['tunnel_id', '=', 61, PDO::PARAM_INT]
            ]
        )) {
            $tunnel_subscribtion = APP::Module('DB')->Select(
                APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_ASSOC], 
                ['id', 'user_id', 'state'], 'tunnels_users',
                [['id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT]]
            );
        } else {
            APP::Render('pages/products/101office/5');
            exit;
        }

        // Если туннель не активный
        if ($tunnel_subscribtion['state'] != 'active') {
            APP::Render('pages/products/101office/5', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получение массива меток
        // Получение массива меток
        $tunnel_subscription_labels = [];
        
        foreach (APP::Module('DB')->Select(
            APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetchAll', PDO::FETCH_ASSOC], 
            ['label_id', 'token'], 'tunnels_tags',
            [['user_tunnel_id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT]]
        ) as $label) {
            $tunnel_subscription_labels[$label['label_id']][] = $label['token'];
        }

        // Логика отображения вариантов страниц ////////////////////////////////////////


        // Получил 13 письмо
        if (array_search('70', $tunnel_subscription_labels['sendmail']) !== false) {
            APP::Render('pages/products/101office/5', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил 12 письмо
        if (array_search('69', $tunnel_subscription_labels['sendmail']) !== false) {
            $label = APP::Module('DB')->Select(
                APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_ASSOC], 
                ['label_id', 'token', 'UNIX_TIMESTAMP(cr_date) AS cr_date'], 'tunnels_tags',
                [
                    ['user_tunnel_id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT],
                    ['label_id', '=', 'sendmail', PDO::PARAM_STR],
                    ['token', '=', '69', PDO::PARAM_STR]
                ]
            );

            // Дата окончания таймера
            $timer_stop = strtotime('+12 hours', $label['cr_date']);

            // Проверка на окончание таймера
            if ($timer_stop < time()) {
                // Если закончился таймер
                APP::Render('pages/products/101office/5', 'include', $tunnel_subscribtion);
            } else {
                // Если не закончился таймер 
                APP::Render('pages/products/101office/4', 'include', $tunnel_subscribtion);
            }
            exit;


            APP::Render('pages/products/101office/4', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил 9/10/11/12 письмо
        if (
            (array_search('66', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('67', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('68', $tunnel_subscription_labels['sendmail']) !== false)
        ) {
            APP::Render('pages/products/101office/4', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил 8 письмо
        if (array_search('65', $tunnel_subscription_labels['sendmail']) !== false) {
            APP::Render('pages/products/101office/3', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил 7 письмо
        if (array_search('64', $tunnel_subscription_labels['sendmail']) !== false) {
            APP::Render('pages/products/101office/2', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил 1/2/3/4/5/6 письмо
        if (
            (array_search('58', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('59', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('60', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('61', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('62', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('63', $tunnel_subscription_labels['sendmail']) !== false)
        ) {
            APP::Render('pages/products/101office/1', 'include', $tunnel_subscribtion);
            exit;
        }
    }
    
    public function Product101OfficeForm() {
        APP::Render('pages/products/101office/form');
    }
    
    public function Product101OfficeActivation() {
        APP::Render('pages/products/101office/activate');
    }
    
    
    public function ProductimageschoolSale() {
        $cookie_tunnel_subscribtion_id = isset($_COOKIE['tunnel_subscribtion_id']) ? $_COOKIE['tunnel_subscribtion_id'] : 0;
        $tunnel_subscribtion_id = isset(APP::Module('Routing')->get['token']) ? APP::Module('Crypt')->Decode(APP::Module('Routing')->get['token']) : $cookie_tunnel_subscribtion_id;

        if (APP::Module('DB')->Select(
            APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_COLUMN], 
            ['COUNT(id)'], 'tunnels_users',
            [['id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT]]
        )) {
            setcookie('tunnel_subscribtion_id', $tunnel_subscribtion_id, time() + 31556926, '/', '.glamurnenko.ru');
        } else {
            APP::Render('pages/products/imageschool/0');
            exit;
        }
        
        // Проверка ID подписки на туннель и получение информации о подписке
        if (APP::Module('DB')->Select(
            APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_COLUMN], 
            ['COUNT(id)'], 'tunnels_users',
            [
                ['id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT],
                ['tunnel_id', '=', 62, PDO::PARAM_INT]
            ]
        )) {
            $tunnel_subscribtion = APP::Module('DB')->Select(
                APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_ASSOC], 
                ['id', 'user_id', 'state'], 'tunnels_users',
                [['id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT]]
            );
        } else {
            APP::Render('pages/products/imageschool/0');
            exit;
        }


        // Если туннель не активный, то 5 вариант
        if ($tunnel_subscribtion['state'] != 'active') {
            APP::Render('pages/products/imageschool/0');
            exit;
        }

        // Получение массива меток
        $tunnel_subscription_labels = Array();

        foreach (APP::Module('DB')->Select(
            APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetchAll', PDO::FETCH_ASSOC], 
            ['label_id', 'token'], 'tunnels_tags',
            [['user_tunnel_id', '=', $tunnel_subscribtion_id, PDO::PARAM_INT]]
        ) as $label) {
            $tunnel_subscription_labels[$label['label_id']][] = $label['token'];
        }

        // Логика отображения вариантов страниц ////////////////////////////////////////

        // Получил 18/19/20 письмо
        if (
            (array_search('74', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('75', $tunnel_subscription_labels['sendmail']) !== false) ||
            (array_search('76', $tunnel_subscription_labels['sendmail']) !== false)
        ) {
            APP::Render('pages/products/imageschool/0', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил 16/17 письмо
        if (
            (array_search('77', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('78', $tunnel_subscription_labels['sendmail']) !== false)
        ) {
            APP::Render('pages/products/imageschool/2', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил 8/9/10/11/12/13/14/15 письмо
        if (
            (array_search('79', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('80', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('81', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('82', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('83', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('84', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('85', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('86', $tunnel_subscription_labels['sendmail']) !== false)
        ) {
            APP::Render('pages/products/imageschool/1', 'include', $tunnel_subscribtion);
            exit;
        }

        // Получил 1/2/3/4/5/6/7 письмо
        if (
            (array_search('87', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('88', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('89', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('90', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('91', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('92', $tunnel_subscription_labels['sendmail']) !== false) || 
            (array_search('93', $tunnel_subscription_labels['sendmail']) !== false)
        ) {
            APP::Render('pages/products/imageschool/0', 'include', $tunnel_subscribtion);
            exit;
        }
    }
    
}
