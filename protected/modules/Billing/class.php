<?
class Billing {

    public $settings;
    
    private $products_search;
    private $invoices_search;
    private $payments_search;
    
    private $products_actions;
    private $invoices_actions;
    private $payments_actions;

    function __construct($conf) {
        foreach ($conf['routes'] as $route) APP::Module('Routing')->Add($route[0], $route[1], $route[2]);
    }

    public function Init() {
        $this->settings = APP::Module('Registry')->Get([
            'module_billing_db_connection',
            'module_billing_sales_tool'
        ]);

        $this->products_search  = new ProductsSearch();
        $this->invoices_search  = new InvoicesSearch();
        $this->payments_search  = new PaymentsSearch();
        
        $this->products_actions = new ProductsActions();
        $this->invoices_actions = new InvoicesActions();
        $this->payments_actions = new PaymentsActions();
        $this->UpdateExchangeRate();
    }

    
    public function Admin() {
        return APP::Render('billing/admin/nav', 'content');
    }
    
    public function Dashboard() {
        return APP::Render('billing/admin/dashboard/index', 'return');
    }
    

    public function ProductsSearch($rules) {
        $out = Array();

        foreach ((array) $rules['rules'] as $rule) {
            $out[] = array_flip((array) $this->products_search->{$rule['method']}($rule['settings']));
        }

        if (array_key_exists('children', (array) $rules)) {
            $out[] = array_flip((array) $this->ProductsSearch($rules['children']));
        }

        if (count($out) > 1) {
            switch ($rules['logic']) {
                case 'intersect': return array_keys((array) call_user_func_array('array_intersect_key', $out));
                    break;
                case 'merge': return array_keys((array) call_user_func_array('array_replace', $out));
                    break;
            }
        } else {
            return array_keys($out[0]);
        }
    }
    
    public function InvoicesSearch($rules) {
        $out = Array();

        foreach ((array) $rules['rules'] as $rule) {
            $out[] = array_flip((array) $this->invoices_search->{$rule['method']}($rule['settings']));
        }

        if (array_key_exists('children', (array) $rules)) {
            $out[] = array_flip((array) $this->InvoicesSearch($rules['children']));
        }

        if (count($out) > 1) {
            switch ($rules['logic']) {
                case 'intersect': return array_keys((array) call_user_func_array('array_intersect_key', $out));
                    break;
                case 'merge': return array_keys((array) call_user_func_array('array_replace', $out));
                    break;
            }
        } else {
            return array_keys($out[0]);
        }
    }
    
    public function PaymentsSearch($rules) {
        $out = Array();

        foreach ((array) $rules['rules'] as $rule) {
            $out[] = array_flip((array) $this->payments_search->{$rule['method']}($rule['settings']));
        }

        if (array_key_exists('children', (array) $rules)) {
            $out[] = array_flip((array) $this->PaymentsSearch($rules['children']));
        }

        if (count($out) > 1) {
            switch ($rules['logic']) {
                case 'intersect': return array_keys((array) call_user_func_array('array_intersect_key', $out));
                    break;
                case 'merge': return array_keys((array) call_user_func_array('array_replace', $out));
                    break;
            }
        } else {
            return array_keys($out[0]);
        }
    }
    
    
    public function CreateInvoice($user_id, $author, $products, $state, $comment = false, $date = false) {
        $amount = 0;
        $invoice_products = [];

        foreach ($products as $product) {
            $amount += $product['amount'];
            $invoice_products[] = $product['id'];
        }

        $invoice_id = APP::Module('DB')->Insert(
            $this->settings['module_billing_db_connection'], 'billing_invoices', [
                'id'      => 'NULL',
                'user_id' => [$user_id, PDO::PARAM_INT],
                'amount'  => [$amount, PDO::PARAM_INT],
                'state'   => [$state, PDO::PARAM_STR],
                'author'  => [$author, PDO::PARAM_INT],
                'up_date' => $date ? [$date, PDO::PARAM_STR] : 'NOW()',
                'cr_date' => $date ? [$date, PDO::PARAM_STR] : 'NOW()'
            ]
        );

        foreach ($products as $product) {
            APP::Module('DB')->Insert(
                $this->settings['module_billing_db_connection'], 'billing_invoices_products', [
                    'id' => 'NULL',
                    'invoice' => [$invoice_id, PDO::PARAM_INT],
                    'type' => ['primary', PDO::PARAM_STR],
                    'product' => [$product['id'], PDO::PARAM_INT],
                    'amount' => [$product['amount'], PDO::PARAM_INT],
                    'cr_date' => $date ? [$date, PDO::PARAM_STR] : 'NOW()'
                ]
            );
        }

        $invoice_data = [
            'invoice_id' => $invoice_id,
            'user' => $user_id,
            'author' => $author,
            'state' => $state,
            'amount' => $amount,
            'products' => $products
        ];
        
        APP::Module('DB')->Insert(
            $this->settings['module_billing_db_connection'], 'billing_invoices_tag', [
                'id' => 'NULL',
                'invoice' => [$invoice_id, PDO::PARAM_INT],
                'action' => ['create_invoice', PDO::PARAM_STR],
                'action_data' => [json_encode($invoice_data), PDO::PARAM_STR],
                'cr_date' => $date ? [$date, PDO::PARAM_STR] : 'NOW()'
            ]
        );
        
        if ($state == 'success') {
            APP::Module('DB')->Insert(
                $this->settings['module_billing_db_connection'], 'billing_payments',
                [
                    'id' => 'NULL',
                    'invoice' => [$invoice_id, PDO::PARAM_INT],
                    'method' => ['admin', PDO::PARAM_STR],
                    'cr_date' => $date ? [$date, PDO::PARAM_STR] : 'NOW()'
                ]
            );
        }
        
        if ($comment) {
            $comment_object_type = APP::Module('DB')->Select(APP::Module('Comments')->settings['module_comments_db_connection'], ['fetchColumn', 0], ['id'], 'comments_objects', [['name', '=', "Invoice", PDO::PARAM_STR]]);
            
            APP::Module('DB')->Insert(
                APP::Module('Comments')->settings['module_comments_db_connection'], ' comments_messages',
                [
                    'id' => 'NULL',
                    'sub_id' => [0, PDO::PARAM_INT],
                    'user' => [APP::Module('Users')->user['id'], PDO::PARAM_INT],
                    'object_type' => [$comment_object_type, PDO::PARAM_INT],
                    'object_id' => [$invoice_id, PDO::PARAM_INT],
                    'message' => [$comment, PDO::PARAM_STR],
                    'url' => [APP::Module('Routing')->root . 'admin/billing/invoices/details/' . $invoice_id, PDO::PARAM_STR],
                    'up_date' => $date ? [$date, PDO::PARAM_STR] : 'NOW()'
                ]
            );
        }
        
        switch ($state) {
            case 'success':
                $this->AddMembersAccessTask($invoice_id);
                $this->AddSecondaryProductsTask($invoice_id);
                break;
        }

        APP::Module('Triggers')->Exec('create_invoice', $invoice_data);

        return $invoice_data;
    }

    
    public function AddMembersAccessTask($invoice_id) {
        $out = [];
        
        $user_id = APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], ['fetch', PDO::FETCH_COLUMN],
            ['user_id'], 'billing_invoices',
            [['id', '=', $invoice_id, PDO::PARAM_INT]]
        );
        
        foreach (APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], 
            ['members_access'], 'billing_products', 
            [['id', 'IN', APP::Module('DB')->Select(
                $this->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], 
                ['product'], 'billing_invoices_products', 
                [['invoice', '=', $invoice_id, PDO::PARAM_INT]]
            ), PDO::PARAM_STR]]
        ) as $members_access) {
            foreach ((array) json_decode($members_access, true) as $item) {
                $item_data = [
                    'type' => substr($item['id'], 0, 1),
                    'id' => substr($item['id'], 1)
                ];

                switch ($item_data['type']) {
                    case 'p': $table = 'members_pages'; break;
                    case 'g': $table = 'members_pages_groups'; break;
                }
                
                if (APP::Module('DB')->Select(
                    APP::Module('Members')->settings['module_members_db_connection'], ['fetch', PDO::FETCH_COLUMN],
                    ['COUNT(id)'], $table,
                    [['id', '=', $item_data['id'], PDO::PARAM_INT]]
                )) {
                    if (!APP::Module('DB')->Select(
                        APP::Module('Members')->settings['module_members_db_connection'], ['fetch', PDO::FETCH_COLUMN],
                        ['COUNT(id)'], 'members_access',
                        [
                            ['user_id', '=', $user_id, PDO::PARAM_INT],
                            ['item', '=', $item_data['type'], PDO::PARAM_STR],
                            ['item_id', '=', $item_data['id'], PDO::PARAM_INT]
                        ]
                    )) {  
                        $out[] = APP::Module('TaskManager')->Add(
                            'Billing', 'ExecMembersAccessTask', 
                            date('Y-m-d H:i:s', strtotime($item['timeout'])), 
                            json_encode([$invoice_id, $user_id, $item_data['type'], $item_data['id']]), 
                            'user_' . $user_id . '_add_member_access', 
                            'wait'
                        );
                    }
                } else {
                    APP::Module('DB')->Insert(
                        $this->settings['module_billing_db_connection'], 'billing_invoices_tag', [
                            'id' => 'NULL',
                            'invoice' => [$invoice_id, PDO::PARAM_INT],
                            'action' => ['fail_open_access', PDO::PARAM_STR],
                            'action_data' => [json_encode($item_data), PDO::PARAM_STR],
                            'cr_date' => 'NOW()'
                        ]
                    );
                }
            }
        }

        APP::Module('Triggers')->Exec('add_members_access_task', [
            'invoice_id' => $invoice_id,
            'out' => $out
        ]);

        return $out;
    }
    
    public function AddSecondaryProductsTask($invoice_id) {
        $out = [];
        
        foreach (APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], 
            ['secondary_products'], 'billing_products', 
            [['id', 'IN', APP::Module('DB')->Select(
                $this->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], 
                ['product'], 'billing_invoices_products', 
                [['invoice', '=', $invoice_id, PDO::PARAM_INT]]
            ), PDO::PARAM_STR]]
        ) as $secondary_products) {
            foreach ((array) json_decode($secondary_products, true) as $product) {
                $out[] = APP::Module('TaskManager')->Add(
                    'Billing', 'ExecSecondaryProductsTask', 
                    date('Y-m-d H:i:s', strtotime($product['timeout'])), 
                    json_encode([$invoice_id, $product['id']]), 
                    'user_' . APP::Module('DB')->Select(
                        $this->settings['module_billing_db_connection'], ['fetch', PDO::FETCH_COLUMN], 
                        ['user_id'], 'billing_invoices', 
                        [['id', '=', $invoice_id, PDO::PARAM_INT]]
                    ) . '_add_secondary_product', 
                    'wait'
                );
            }
        }

        APP::Module('Triggers')->Exec('add_secondary_products_task', [
            'invoice_id' => $invoice_id,
            'out' => $out
        ]);

        return $out;
    }
    
    
    public function ExecMembersAccessTask($invoice_id, $user_id, $object_type, $object_id) {
        $access_id = APP::Module('DB')->Insert(
            APP::Module('Members')->settings['module_members_db_connection'], 'members_access', [
                'id' => 'NULL',
                'user_id' => [$user_id, PDO::PARAM_INT],
                'item' => [$object_type, PDO::PARAM_STR],
                'item_id' => [$object_id, PDO::PARAM_INT],
                'cr_date' => 'NOW()'
            ]
        );

        APP::Module('DB')->Insert(
            $this->settings['module_billing_db_connection'], 'billing_invoices_tag', [
                'id' => 'NULL',
                'invoice_id' => [$invoice_id, PDO::PARAM_INT],
                'action' => ['success_open_access', PDO::PARAM_STR],
                'action_data' => [json_encode([$object_type, $object_id]), PDO::PARAM_STR],
                'cr_date' => 'NOW()'
            ]
        );

        APP::Module('Triggers')->Exec('open_members_access', [
            'invoice_id' => $invoice_id,
            'access_id' => $access_id
        ]);

        return $access_id;
    }
    
    public function ExecSecondaryProductsTask($invoice_id, $product_id) {
        $invoice_product_id = APP::Module('DB')->Insert(
            $this->settings['module_billing_db_connection'], 'billing_invoices_products', [
                'id' => 'NULL',
                'invoice' => [$invoice_id, PDO::PARAM_INT],
                'type' => ['secondary', PDO::PARAM_STR],
                'product' => [$product_id, PDO::PARAM_INT],
                'amount' => [0, PDO::PARAM_INT],
                'cr_date' => 'NOW()'
            ]
        );
        
        $this->AddMembersAccessTask($invoice_id);

        APP::Module('DB')->Insert(
            $this->settings['module_billing_db_connection'], 'billing_invoices_tag', [
                'id' => 'NULL',
                'invoice_id' => [$invoice_id, PDO::PARAM_INT],
                'action' => ['add_secondary_product', PDO::PARAM_STR],
                'action_data' => [json_encode(['product' => $product_id]), PDO::PARAM_STR],
                'cr_date' => 'NOW()'
            ]
        );
        
        APP::Module('Triggers')->Exec('add_secondary_product', [
            'invoice_id' => $invoice_id,
            'invoice_product_id' => $invoice_product_id
        ]);

        return $invoice_product_id;
    }
    

    public function ManageProducts() {
        APP::Render('billing/admin/products/index');
    }
    
    public function ManageInvoices() {
        APP::Render('billing/admin/invoices/index');
    }
    
    public function ManagePayments() {
        APP::Render('billing/admin/payments/index');
    }

    public function AddProduct() {
        APP::Render(
            'billing/admin/products/add', 'include', [
            'products_list' => APP::Module('DB')->Select(
                $this->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_ASSOC], ['id', 'name', 'amount', 'access_link', 'descr_link', 'members_access'], 'billing_products'
            ),
        ]);
    }
    
    public function AddInvoice() {
        $out = [];
        $out['comment'] = isset(APP::Module('Routing')->get['comment']) ? APP::Module('Routing')->get['comment'] : '';
        $out['date'] = isset(APP::Module('Routing')->get['date']) ? APP::Module('Routing')->get['date'] : '';
        $out['user'] = isset(APP::Module('Routing')->get['user']) ? APP::Module('Routing')->get['user'] : '';
        $out['state'] = isset(APP::Module('Routing')->get['state']) ? APP::Module('Routing')->get['state'] : 'new';
        
        APP::Render(
            'billing/admin/invoices/add', 'include', [
            'products_list' => APP::Module('DB')->Select(
                $this->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_ASSOC], ['name', 'amount', 'id'], 'billing_products'
            ),
            'invoice_data' => $out
        ]);
    }
    

    public function InvoiceDetails() {
        $invoice_id = APP::Module('Crypt')->Decode(APP::Module('Routing')->get['invoice_id_hash']);
        
        APP::Render(
            'billing/admin/invoices/details',
            'include', 
            [
                'invoice' => APP::Module('DB')->Select(
                    APP::Module('Billing')->settings['module_billing_db_connection'], ['fetch', PDO::FETCH_ASSOC],
                    [
                        'id', 
                        'amount', 
                        'state', 
                        'author',
                        'up_date',
                        'cr_date'
                    ], 
                    'billing_invoices',
                    [['id', '=', $invoice_id, PDO::PARAM_INT]]
                ),
                'details' => APP::Module('DB')->Select(
                    APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_ASSOC],
                    [
                        'id', 
                        'item', 
                        'value'
                    ], 
                    'billing_invoices_details',
                    [['invoice', '=', $invoice_id, PDO::PARAM_INT]]
                ),
                'products' => APP::Module('DB')->Select(
                    APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_ASSOC],
                    [
                        'billing_invoices_products.id', 
                        'billing_invoices_products.type', 
                        'billing_invoices_products.product',
                        'billing_invoices_products.amount',
                        'billing_invoices_products.cr_date',
                        
                        'billing_products.name'
                    ], 
                    'billing_invoices_products',
                    [['billing_invoices_products.invoice', '=', $invoice_id, PDO::PARAM_INT]],
                    [
                        'join/billing_products' => [['billing_products.id', '=', 'billing_invoices_products.product']]
                    ],
                    ['billing_invoices_products.id'],
                    false,
                    ['billing_invoices_products.cr_date', 'ASC']
                ),
                'tags' => APP::Module('DB')->Select(
                    APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_ASSOC],
                    [
                        'id', 
                        'action', 
                        'action_data',
                        'cr_date'
                    ], 
                    'billing_invoices_tag',
                    [['invoice', '=', $invoice_id, PDO::PARAM_INT]]
                ),
                'payments' => APP::Module('DB')->Select(
                    APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_ASSOC],
                    [
                        'id', 
                        'method',
                        'cr_date'
                    ], 
                    'billing_payments',
                    [['invoice', '=', $invoice_id, PDO::PARAM_INT]]
                )
            ]
        );
    }
    
    public function EditProduct() {
        $product_id = APP::Module('Crypt')->Decode(APP::Module('Routing')->get['product_id_hash']);

        $product  = APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], ['fetch', PDO::FETCH_ASSOC], 
            ['name', 'amount', 'members_access', 'secondary_products', 'access_link', 'descr_link'], 'billing_products', 
            [['id', '=', $product_id, PDO::PARAM_INT]]
        );
        
        $product['members_access'] = json_decode($product['members_access'], true);
        $product['secondary_products'] = json_decode($product['secondary_products'], true);

        $products = [];
        
        foreach (APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_ASSOC], 
            ['name', 'amount', 'id'], 'billing_products'
        ) as $item) {
            $products[$item['id']] = $item;
        }

        APP::Render(
            'billing/admin/products/edit', 'include', [
                'product'  => $product,
                'products_list' => $products,
            ]
        );
    }

    public function Settings() {
        APP::Render('billing/admin/settings');
    }
    
    public function ImportInvoices() {
        if (isset($_FILES['invoices'])) {
            $users = [];
            $imported = [];

            foreach (file($_FILES['invoices']['tmp_name']) as $string) {
                $invoice = explode(';', trim($string));
                
                for ($i = 0; $i <= 10; $i ++) {
                    if (!isset($invoice[$i])) {
                        $invoice[$i] = false;
                    } 
                }

                if ((((!$invoice[0]) || (!$invoice[1]) || (!$invoice[2]) || (!$invoice[4])))) {
                    continue;
                }

                $user = APP::Module('DB')->Select(
                    APP::Module('Users')->settings['module_users_db_connection'], ['fetch', PDO::FETCH_ASSOC],
                    ['id', 'UNIX_TIMESTAMP(reg_date) AS reg_date'], 'users',
                    [
                        ['email', '=', $invoice[0], PDO::PARAM_STR]
                    ]
                );

                if (!isset($user['id'])) {
                    continue;
                }
                
                if ((int) strtotime($invoice[4]) < (int) $user['reg_date']) {
                    continue;
                }
                
                $product_id = APP::Module('DB')->Select(
                    $this->settings['module_billing_db_connection'], ['fetch', PDO::FETCH_COLUMN],
                    ['id'], 'billing_products',
                    [
                        ['name', '=', $invoice[1], PDO::PARAM_STR]
                    ]
                );
                
                if (!$product_id) {
                    continue;
                }
                
                $invoice[11] = $product_id;
                
                if (isset($users[$user['id']])) {
                    $users[$user['id']]['invoices'][] = $invoice;
                    $users[$user['id']]['sum'][] = $invoice[2];
                } else {
                    $users[$user['id']] = [
                        'user_id' => $user['id'],
                        'email' => $invoice[0],
                        'reg_date' => $user['reg_date'],
                        'invoices' => [$invoice],
                        'sum' => [$invoice[2]]
                    ];
                }
            }

            foreach ($users as $user_id => $user_data) {
                $users[$user_id]['pult_sum'] = (int) APP::Module('DB')->Select(
                    $this->settings['module_billing_db_connection'], ['fetch', PDO::FETCH_COLUMN],
                    ['SUM(amount)'], 'billing_invoices',
                    [
                        ['user_id', '=', $user_id, PDO::PARAM_INT],
                        ['state', '=', 'success', PDO::PARAM_STR],
                        ['cr_date', 'BETWEEN', '"' . $_POST['date_year_from'] . '-' . $_POST['date_month_from'] . '-' . $_POST['date_day_from'] . ' 00:00:00" AND "' . $_POST['date_year_to'] . '-' . $_POST['date_month_to'] . '-' . $_POST['date_day_to'] . ' 00:00:00"', PDO::PARAM_STR]
                    ]
                );

                if ($users[$user_id]['pult_sum'] >= (int) array_sum($user_data['sum'])) {
                    unset($users[$user_id]);
                    continue;
                }
                
                $users[$user_id]['diff_sum'] = array_sum($user_data['sum']) - $users[$user_id]['pult_sum'];

                if (((int)$users[$user_id]['pult_sum'] === 0) && ((int) $users[$user_id]['diff_sum'] !== 0)) {
                    foreach ($user_data['invoices'] as $user_invoice) {
                        $this->CreateInvoice(
                            $user_id, 
                            APP::Module('Users')->user['id'], 
                            [
                                [
                                    'id' => $user_invoice[11],
                                    'amount' => $user_invoice[2]
                                ]
                            ], 
                            'success',
                            $user_invoice[10],
                            $user_invoice[4]
                        );

                        $imported[] = $user_invoice;
                    }
                    
                    unset($users[$user_id]);
                }
            }
            
            APP::Render('billing/admin/invoices/import', 'include', [
                'action' => 'import',
                'users' => $users,
                'imported' => $imported
            ]);
        } else {
            APP::Render('billing/admin/invoices/import', 'include', [
                'action' => 'form'
            ]);
        }
    }
    

    public function DiffInvoices() {
        if (isset($_FILES['source'])) {
            $users = [];
            $invoices = [];

            foreach (file($_FILES['source']['tmp_name']) as $string) {
                $invoice = explode(';', trim($string));

                if (!isset($email_match[1])) {
                    continue;
                }

                $pult_user = APP::Module('DB')->Select(
                    APP::Module('Users')->settings['module_users_db_connection'], ['fetch', PDO::FETCH_ASSOC],
                    ['id', 'reg_date'], 'users',
                    [
                        ['email', '=', $email_match[1], PDO::PARAM_STR]
                    ]
                );

                if (!isset($pult_user['id'])) {
                    continue;
                }

                $users[$email_match[1] . '|' . $pult_user['id'] . '|' . strtotime($pult_user['reg_date'])][] = $item[4];

                $invoices[$pult_user['id']][] = [
                    $item[2], $item[3], $item[4], $item[5], $item[8], $item[9], $item[10], $item[11]
                ];
            }
            ?>
            <table border="1" width="100%">
                <tr>
                    <td>E-Mail</td>
                    <td>Дата регистрации</td>
                    <td>Сумма из отчета</td>
                    <td>Сумма всех счетов у нас</td>
                    <td>Не хватает</td>
                    <td>Счета из отчета</td>
                </tr>
                <?
                foreach ($users as $usr_item => $sum) {
                    $id = explode('|', $usr_item);
                
                    $sum2 = APP::Module('DB')->Select(
                        APP::Module('Billing')->settings['module_billing_db_connection'], ['fetch', PDO::FETCH_COLUMN],
                        ['SUM(amount)'], 'billing_invoices',
                        [
                            ['user_id', '=', $id[1], PDO::PARAM_INT],
                            ['state', '=', 'success', PDO::PARAM_STR]
                        ]
                    );

                    if ((int) $sum2 >= (int) array_sum($sum)) {
                        continue;
                    }

                    if (((int) $sum2 == 0) && ((int) array_sum($sum) != 0)) {
                        $invoice_email = $id[0];

                        foreach ($invoices[$id[1]] as $invoice) {
                            $inv_date = explode('.', $invoice[4]);

                            if (count($inv_date) == 3) {
                                $inv_prod_id = APP::Module('DB')->Select(
                                    APP::Module('Billing')->settings['module_billing_db_connection'], ['fetch', PDO::FETCH_COLUMN],
                                    ['id'], 'billing_products',
                                    [
                                        ['name', '=', $invoice[3], PDO::PARAM_STR]
                                    ]
                                );

                                if ($inv_prod_id) {
                                    $invoice[8] = '20' . $inv_date[2] . '-' . $inv_date[1] . '-' . $inv_date[0] . ' 12:00:00';
                                    $invoice[9] = $id[0];
                                    $invoice[10] = $inv_prod_id;

                                    if ((int) strtotime($invoice[8]) > (int) $id[2]) {
                                        ?>
                                        <tr>
                                            <td><?= $id[0] ?></td>
                                            <td><?= date('Y-m-d', $id[2]) ?></td>
                                            <td><?= array_sum($sum) ?></td>
                                            <td><?= $sum2 ?></td>
                                            <td><?= array_sum($sum) - $sum2 ?></td>
                                            <td>
                                                <table border="1" width="100%">
                                                    <?
                                                    foreach ($invoices[$id[1]] as $invoice) {
                                                        echo '<tr><td width="11%">' . implode('</td><td width="11%">', $invoice) . '</td><td width="11%"><a target="_blank" href="' . APP::Module('Routing')->root . 'admin/billing/invoices/add?user=' . $id[0] . '&state=success&comment=import' . date('Ymd') . '">Добавить</a></td></tr>';
                                                    }
                                                    ?>
                                                </table>
                                            </td>
                                        </tr>
                                        <?
                                    }
                                }
                            }
                        }
                    }
                }
            ?>
            </table>
            <?
        } else {
            APP::Render('billing/admin/import', 'include', 'form');
        }
    }
    
    public function DiffImportInvoices() {
        if (isset($_FILES['source'])) {
            $users = [];
            $invoices = [];

            foreach (file($_FILES['source']['tmp_name']) as $string) {
                $item = explode(',', trim($string));

                preg_match('/\<(.*)\>/', $item[1], $email_match);

                if (!isset($email_match[1])) {
                    continue;
                }

                $pult_user = APP::Module('DB')->Select(
                    APP::Module('Users')->settings['module_users_db_connection'], ['fetch', PDO::FETCH_ASSOC],
                    ['id', 'reg_date'], 'users',
                    [
                        ['email', '=', $email_match[1], PDO::PARAM_STR]
                    ]
                );

                if (!isset($pult_user['id'])) {
                    continue;
                }

                $users[$email_match[1] . '|' . $pult_user['id'] . '|' . strtotime($pult_user['reg_date'])][] = $item[4];

                $invoices[$pult_user['id']][] = [
                    $item[2], $item[3], $item[4], $item[5], $item[8], $item[9], $item[10], $item[11]
                ];
            }

            foreach ($users as $usr_item => $sum) {
                $id = explode('|', $usr_item);
                
                $sum2 = APP::Module('DB')->Select(
                    APP::Module('Billing')->settings['module_billing_db_connection'], ['fetch', PDO::FETCH_COLUMN],
                    ['SUM(amount)'], 'billing_invoices',
                    [
                        ['user_id', '=', $id[1], PDO::PARAM_INT],
                        ['state', '=', 'success', PDO::PARAM_STR]
                    ]
                );

                if ((int) $sum2 >= (int) array_sum($sum)) {
                    continue;
                }

                if (((int) $sum2 == 0) && ((int) array_sum($sum) != 0)) {
                    $invoice_email = $id[0];

                    foreach ($invoices[$id[1]] as $invoice) {
                        $inv_date = explode('.', $invoice[4]);
                        
                        if (count($inv_date) == 3) {
                            $inv_prod_id = APP::Module('DB')->Select(
                                APP::Module('Billing')->settings['module_billing_db_connection'], ['fetch', PDO::FETCH_COLUMN],
                                ['id'], 'billing_products',
                                [
                                    ['name', '=', $invoice[3], PDO::PARAM_STR]
                                ]
                            );
                            
                            if ($inv_prod_id) {
                                $invoice[8] = '20' . $inv_date[2] . '-' . $inv_date[1] . '-' . $inv_date[0] . ' 12:00:00';
                                $invoice[9] = $id[0];
                                $invoice[10] = $inv_prod_id;
                                
                                if ((int) strtotime($invoice[8]) > (int) $id[2]) {
                                    $message = implode('<br>', $invoice);

                                    if (!APP::Module('DB')->Select(
                                        APP::Module('Comments')->settings['module_comments_db_connection'], ['fetchColumn', 0],
                                        ['COUNT(id)'], 'comments_messages',
                                        [['MD5(message)', '=', md5($message), PDO::PARAM_STR]]
                                    )) {
                                        $this->CreateInvoice(
                                            $id[1], 
                                            0, 
                                            [
                                                [
                                                    'id' => $invoice[10],
                                                    'amount' => $invoice[2]
                                                ]
                                            ], 
                                            'success',
                                            $message,
                                            $invoice[8]
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            echo 'Готово!';
        } else {
            ?>
            <form enctype="multipart/form-data" method="POST">
                <input name="source" type="file">
                <input type="submit" value="Импортировать" />
            </form>
            <?
        }
    }
    
    public function StatProducts() {
        $out = [];
        
        foreach (APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_ASSOC], 
            [
                'id',
                'name'
            ], 
            'billing_products'
        ) as $product) {
            $filter = [
                ['id', 'IN', APP::Module('DB')->Select(
                    $this->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], 
                    [
                        'invoice'
                    ], 
                    'billing_invoices_products',
                    [
                        ['product', '=', $product['id'], PDO::PARAM_INT]
                    ]
                )],
                ['state', '=', 'success', PDO::PARAM_STR],
                ['amount', '!=', '0', PDO::PARAM_INT]
            ];
            
            if (isset(APP::Module('Routing')->get['date'])) {
                $filter = array_merge($filter, [
                    ['cr_date', 'BETWEEN', '"' . APP::Module('Routing')->get['date']['from'] . ' 00:00:00" AND "' . APP::Module('Routing')->get['date']['to'] . ' 00:00:00"', PDO::PARAM_STR]
                ]);
            }
            
            $out[] = [
                'name' => $product['name'],
                'invoices' => APP::Module('DB')->Select(
                    $this->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], 
                    [
                        'id'
                    ], 
                    'billing_invoices',
                    $filter
                ),
                'sum' => (int) APP::Module('DB')->Select(
                    $this->settings['module_billing_db_connection'], ['fetch', PDO::FETCH_COLUMN], 
                    [
                        'SUM(amount)'
                    ], 
                    'billing_invoices',
                    $filter
                )
            ];
        }

        APP::Render('billing/admin/products/stat', 'include', $out);
    }

    
    public function APIDashboard() {
        $tmp = [];
        
        $metrics = [
            'new',
            'processed',
            'success',
            'revoked'
        ];
        
        for ($x = $_POST['date']['to']; $x >= $_POST['date']['from']; $x = $x - 86400) {
            foreach ($metrics as $value) {
                $tmp[$value][date('d-m-Y', $x)] = [];
            }
        }

        foreach (APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_ASSOC], 
            [
                'amount',
                'state',
                'UNIX_TIMESTAMP(cr_date) AS date'
            ], 
            'billing_invoices',
            [['UNIX_TIMESTAMP(cr_date)', 'BETWEEN', $_POST['date']['from'] . ' AND ' . $_POST['date']['to']]]
        ) as $data) {
            $tmp[$data['state']][date('d-m-Y', $data['date'])][] = $data['amount'];
        }

        $out = [
            'range' => [],
            'total' => [
                'new' => APP::Module('Crypt')->Encode('{"logic":"intersect","rules":[{"method":"cr_date","settings":{"date_from":' . $_POST['date']['from'] . ',"date_to":' . $_POST['date']['to'] . '}},{"method":"state","settings":{"logic":"=","value":"new"}}]}'),
                'processed' => APP::Module('Crypt')->Encode('{"logic":"intersect","rules":[{"method":"cr_date","settings":{"date_from":' . $_POST['date']['from'] . ',"date_to":' . $_POST['date']['to'] . '}},{"method":"state","settings":{"logic":"=","value":"processed"}}]}'),
                'revoked' => APP::Module('Crypt')->Encode('{"logic":"intersect","rules":[{"method":"cr_date","settings":{"date_from":' . $_POST['date']['from'] . ',"date_to":' . $_POST['date']['to'] . '}},{"method":"state","settings":{"logic":"=","value":"revoked"}}]}'),
                'success' => APP::Module('Crypt')->Encode('{"logic":"intersect","rules":[{"method":"cr_date","settings":{"date_from":' . $_POST['date']['from'] . ',"date_to":' . $_POST['date']['to'] . '}},{"method":"state","settings":{"logic":"=","value":"success"}}]}')
            ]
        ];

        foreach ((array) $tmp as $source => $dates) {
            foreach ((array) $dates as $key => $value) {
                $out['range'][$source][$key] = [
                    strtotime($key) * 1000, 
                    count($value), 
                    array_sum($value),
                    APP::Module('Crypt')->Encode('{"logic":"intersect","rules":[{"method":"cr_date","settings":{"date_from":' . strtotime($key) . ',"date_to":' . strtotime($key . ' + 1 day') . '}},{"method":"state","settings":{"logic":"=","value":"' . $source . '"}}]}')
                ];
            }
        }
        
        foreach ($out['range'] as $key => $value) {
            $out['range'][$key] = array_values($value);
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');
        
        echo json_encode($out);
        exit;
    }
    
    public function APISearchProducts() {
        $request = json_decode(file_get_contents('php://input'), true);
        $out     = $this->ProductsSearch(json_decode($request['search'], 1));
        $rows    = [];

        foreach (APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_ASSOC], ['id', 'name', 'amount', 'up_date'], 'billing_products', [['id', 'IN', $out, PDO::PARAM_INT]], false, false, false, [$request['sort_by'], $request['sort_direction']], $request['rows'] === -1 ? false : [($request['current'] - 1) * $request['rows'], $request['rows']]
        ) as $row) {
            $row['product_id_token'] = APP::Module('Crypt')->Encode($row['id']);
            array_push($rows, $row);
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode([
            'current'  => $request['current'],
            'rowCount' => $request['rows'],
            'rows'     => $rows,
            'total'    => count($out)
        ]);
        exit;
    }
    
    public function APISearchInvoices() {
        $request = json_decode(file_get_contents('php://input'), true);
        $out     = $this->InvoicesSearch(json_decode($request['search'], 1));
        $rows    = [];

        foreach (APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], 
            ['fetchAll', PDO::FETCH_ASSOC], 
            [
                'billing_invoices.id', 
                'billing_invoices.user_id', 
                'billing_invoices.amount', 
                'billing_invoices.author', 
                'billing_invoices.state', 
                'billing_invoices.up_date',
                
                'users.email AS user_email'
            ], 
            'billing_invoices', 
            [
                ['billing_invoices.id', 'IN', $out, PDO::PARAM_INT]
            ], 
            [
                'join/users' => [['users.id', '=', 'billing_invoices.user_id']]
            ],
            ['billing_invoices.id'],
            false,
            [$request['sort_by'], $request['sort_direction']], 
            $request['rows'] === -1 ? false : [($request['current'] - 1) * $request['rows'], $request['rows']]
        ) as $row) {
            $row['invoice_id_token'] = APP::Module('Crypt')->Encode($row['id']);
            $row['invoice_user_token'] = APP::Module('Crypt')->Encode($row['user_id']);
            
            $row['author_email'] = APP::Module('DB')->Select(
                APP::Module('Users')->settings['module_users_db_connection'], 
                ['fetch', PDO::FETCH_COLUMN], 
                [
                    'email'
                ], 
                'users', 
                [
                    ['id', '=', $row['author'], PDO::PARAM_INT]
                ]
            );
            
            $row['invoice_author_token'] = APP::Module('Crypt')->Encode($row['author']);
            
            array_push($rows, $row);
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode([
            'current'  => $request['current'],
            'rowCount' => $request['rows'],
            'rows'     => $rows,
            'total'    => count($out)
        ]);
        exit;
    }
    
    public function APISearchPayments() {
        $request = json_decode(file_get_contents('php://input'), true);
        $out     = $this->PaymentsSearch(json_decode($request['search'], 1));
        $rows    = [];

        foreach (APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_ASSOC], ['id', 'invoice_id', 'method', 'cr_date'], 'billing_payments', [['id', 'IN', $out, PDO::PARAM_INT]], false, false, false, [$request['sort_by'], $request['sort_direction']], $request['rows'] === -1 ? false : [($request['current'] - 1) * $request['rows'], $request['rows']]
        ) as $row) {
            $row['payment_id_token'] = APP::Module('Crypt')->Encode($row['id']);
            array_push($rows, $row);
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode([
            'current'  => $request['current'],
            'rowCount' => $request['rows'],
            'rows'     => $rows,
            'total'    => count($out)
        ]);
        exit;
    }

    public function APISearchProductsAction() {
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode($this->products_actions->{$_POST['action']}($this->ProductsSearch(json_decode($_POST['rules'], 1)), isset($_POST['settings']) ? $_POST['settings'] : false));
        exit;
    }
    
    public function APISearchInvoicesAction() {
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode($this->invoices_actions->{$_POST['action']}($this->InvoicesSearch(json_decode($_POST['rules'], 1)), isset($_POST['settings']) ? $_POST['settings'] : false));
        exit;
    }
    
    public function APISearchPaymentsAction() {
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode($this->payments_actions->{$_POST['action']}($this->PaymentsSearch(json_decode($_POST['rules'], 1)), isset($_POST['settings']) ? $_POST['settings'] : false));
        exit;
    }

    
    public function APIAddProduct() {
        $out = [
            'status' => 'success',
            'errors' => []
        ];

        if ($out['status'] == 'success') {
            $members_access = [];
            $secondary_products = [];

            if (isset($_POST['members_access'])) {
                foreach ((array) $_POST['members_access'] as $item) {
                    if ($item['id']) {
                        $members_access[] = $item;
                    }
                }
            }
            
            if (isset($_POST['secondary_products'])) {
                foreach ((array) $_POST['secondary_products'] as $product) {
                    if ((int) $product['id']) {
                        $secondary_products[] = $product;
                    }
                }
            }

            $out['product_id'] = APP::Module('DB')->Insert(
                $this->settings['module_billing_db_connection'], 'billing_products', [
                    'id' => 'NULL',
                    'name' => [$_POST['name'], PDO::PARAM_STR],
                    'amount' => [$_POST['amount'], PDO::PARAM_INT],
                    'members_access' => [json_encode($members_access), PDO::PARAM_STR],
                    'secondary_products' => [json_encode($secondary_products), PDO::PARAM_STR],
                    'access_link' => [$_POST['access_link'], PDO::PARAM_STR],
                    'descr_link' => [$_POST['descr_link'], PDO::PARAM_STR],
                    'up_date' => 'NOW()'
                ]
            );

            APP::Module('Triggers')->Exec('add_product', [
                'id' => $out['product_id'],
                'data' => $_POST
            ]);
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode($out);
        exit;
    }
    
    public function APIAddInvoice() {
        $out = [
            'status' => 'success',
            'errors' => []
        ];

        if (is_numeric($_POST['user_id'])) {
            if (!APP::Module('DB')->Select(
                APP::Module('Users')->settings['module_users_db_connection'], ['fetchColumn', 0], ['COUNT(id)'], 'users', [['id', '=', $_POST['user_id'], PDO::PARAM_INT]]
            )) {
                $out['status'] = 'error';
                $out['errors'][] = 1;
            }
        } else {
            if (!APP::Module('DB')->Select(
                APP::Module('Users')->settings['module_users_db_connection'], ['fetchColumn', 0], ['COUNT(id)'], 'users', [['email', '=', $_POST['user_id'], PDO::PARAM_STR]]
            )) {
                $out['status'] = 'error';
                $out['errors'][] = 1;
            }
        }

        if ($out['status'] == 'success') {
            $out['invoice'] = $this->CreateInvoice(
                is_numeric($_POST['user_id']) ? $_POST['user_id'] : APP::Module('DB')->Select(APP::Module('Users')->settings['module_users_db_connection'], ['fetchColumn', 0], ['id'], 'users', [['email', '=', $_POST['user_id'], PDO::PARAM_STR]]), 
                APP::Module('Users')->user['id'], 
                $_POST['products'], 
                $_POST['state'],
                $_POST['comment'],
                $_POST['date']
            );
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode($out);
        exit;
    }
    
    public function APIRemoveProduct() {
        $out = [
            'status' => 'success',
            'errors' => []
        ];

        if (!APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], ['fetchColumn', 0], ['COUNT(id)'], 'billing_products', [['id', '=', $_POST['id'], PDO::PARAM_INT]]
        )) {
            $out['status']   = 'error';
            $out['errors'][] = 1;
        }

        if ($out['status'] == 'success') {
            $out['count'] = APP::Module('DB')->Delete(
                $this->settings['module_billing_db_connection'], 'billing_products', [['id', '=', $_POST['id'], PDO::PARAM_INT]]
            );

            APP::Module('Triggers')->Exec('remove_product', ['id' => $_POST['id'], 'result' => $out['count']]);
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode($out);
        exit;
    }

    public function APIUpdateProduct() {
        $out = [
            'status' => 'success',
            'errors' => []
        ];

        $product_id = APP::Module('Crypt')->Decode($_POST['id']);

        if (!APP::Module('DB')->Select($this->settings['module_billing_db_connection'], ['fetchColumn', 0], ['COUNT(id)'], 'billing_products', [['id', '=', $product_id, PDO::PARAM_INT]])) {
            $out['status']   = 'error';
            $out['errors'][] = 1;
        }

        if ($out['status'] == 'success') {
            APP::Module('DB')->Update($this->settings['module_billing_db_connection'], 'billing_products',
                [
                    'name' => $_POST['name'],
                    'amount' => $_POST['amount'],
                    'members_access' => isset($_POST['members_access']) ? json_encode(array_values($_POST['members_access'])) : NULL,
                    'secondary_products' => isset($_POST['secondary_products']) ? json_encode(array_values($_POST['secondary_products'])) : NULL,
                    'access_link' => $_POST['access_link'],
                    'descr_link' => $_POST['descr_link']
                ],
                [['id', '=', $product_id, PDO::PARAM_INT]]
            );

            APP::Module('Triggers')->Exec('update_product', [
                'id'  => $product_id,
                'name' => $_POST['name'],
                'amount' => $_POST['amount'],
                'members_access' => isset($_POST['members_access']) ? json_encode(array_values($_POST['members_access'])) : NULL,
                'secondary_products' => isset($_POST['secondary_products']) ? json_encode(array_values($_POST['secondary_products'])) : NULL,
                'access_link' => $_POST['access_link'],
                'descr_link' => $_POST['descr_link']
            ]);
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode($out);
        exit;
    }

    public function APIUpdateSettings() {
        if(isset($_POST['module_billing_sales_tool_tunnel'])){
            foreach($_POST['module_billing_sales_tool_tunnel'] as $key => $tunnel){
                $sale[$tunnel] = explode(',', $_POST['module_billing_sales_tool_product'][$key]);
            }

            APP::Module('Registry')->Update(['value' => json_encode($sale)], [['item', '=', 'module_billing_sales_tool', PDO::PARAM_STR]]);
        }

        APP::Module('Registry')->Update(['value' => $_POST['module_billing_db_connection']], [['item', '=', 'module_billing_db_connection', PDO::PARAM_STR]]);

        APP::Module('Triggers')->Exec('update_billing_settings', [
            'db_connection' => $_POST['module_billing_db_connection']
        ]);

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode([
            'status' => 'success',
            'errors' => []
        ]);
        exit;
    }

    public function AddRelatedProducts($user_id, $products) {
        $out['access_id'] = [];

        foreach ($products as $product) {
            $out['access_id'][] = APP::Module('DB')->Insert(
                APP::Module('Members')->settings['module_members_db_connection'], 'related_products', Array(
                    'id'         => 'NULL',
                    'user_id'    => [$user_id, PDO::PARAM_INT],
                    'product_id' => [$product, PDO::PARAM_STR]
                )
            );
        }

        APP::Module('Triggers')->Exec('add_related_products', [
            'user_id'  => $user_id,
            'products' => $products
        ]);

        return $out;
    }

    public function EditInvoice() {
        $invoice_id = APP::Module('Crypt')->Decode(APP::Module('Routing')->get['invoice_id_hash']);

        $products_counter = 0;

        // Формирование списка продуктов
        $products_list = Array();

        foreach (APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_ASSOC], ['name', 'amount', 'id'], 'billing_products'
        ) as $product) {
            $products_list[$product['id']] = [
                'name'   => '[' . $product['id'] . '] ' . $product['name'],
                'amount' => $product['amount']
            ];
        }

        $products = APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_ASSOC], ['product', 'amount', 'id'], 'billing_invoices_products', [['invoice', '=', $invoice_id, PDO::PARAM_INT]]
        );

        // Получение кол-ва продуктов в счете
        $products_counter = APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], ['fetch', PDO::FETCH_COLUMN], ['count(*)'], 'billing_invoices_products'
        );

        APP::Render(
            'billing/admin/invoices/edit', 
            'include', 
            [
                'invoice' => [
                    // Информация о счете
                    'main'     => APP::Module('DB')->Select(
                        $this->settings['module_billing_db_connection'],['fetch', PDO::FETCH_ASSOC],
                        ['users.email','billing_invoices.user_id', 'billing_invoices.state', 'billing_invoices.amount', 'billing_invoices.id', 'UNIX_TIMESTAMP(billing_invoices.up_date) as up_date', 'UNIX_TIMESTAMP(billing_invoices.cr_date) as cr_date'],
                        'billing_invoices', [['billing_invoices.id', '=', $invoice_id, PDO::PARAM_INT]],['join/users'=>[['users.id','=','billing_invoices.user_id']]]
                    ),
                    // Список продуктов счета
                    'products' => $products
                ],
                // Список продуктов
                'products_list' => $products_list,
                // Счетчики
                'counters'      => [
                    'products' => count($products)
                ]
            ]
        );
    }

    public function APIUpdateInvoice() {
        $out = [
            'status'     => 'success',
            'errors'     => [],
            'invoice'    => $_POST['invoice']['id']
        ];

        if (!APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], ['fetchColumn', 0], ['COUNT(id)'], 'billing_invoices', [['id', '=', $_POST['invoice']['id'], PDO::PARAM_INT]]
        )) {
            $out['status']   = 'error';
            $out['errors'][] = 1;
        }

        // Calculate invoice amount
        $amount = 0;

        foreach ((array) $_POST['invoice']['products'] as $product) {
            $amount += (int) $product[1];
        }
        
        // Update invoice
        APP::Module('DB')->Update($this->settings['module_billing_db_connection'],
            'billing_invoices',
            [
                'amount'  => $amount,
                'state'   => $_POST['invoice']['state'],
                'up_date' => date('Y-m-d H:i:s')
            ], 
            [['id', '=', $_POST['invoice']['id'], PDO::PARAM_INT]]
        );

        // Remove invoice products
        APP::Module('DB')->Delete(
            $this->settings['module_billing_db_connection'], 'billing_invoices_products', [['invoice', '=', $_POST['invoice']['id'], PDO::PARAM_INT]]
        );

        // Insert invoice products
        foreach ($_POST['invoice']['products'] as $product) {
            APP::Module('DB')->Insert(
                $this->settings['module_billing_db_connection'], 'billing_invoices_products', Array(
                    'id'            => 'NULL',
                    'invoice'       => [$_POST['invoice']['id'], PDO::PARAM_INT],
                    'type'          => ['primary', PDO::PARAM_STR],
                    'product'       => [$product[0], PDO::PARAM_INT],
                    'amount'        => [$product[1], PDO::PARAM_INT],
                    'cr_date'       => 'NOW()'
                )
            );
        }

        // Сохранение истории
        $data = [
            'state'      => $_POST['invoice']['state'],
            'amount'     => $amount,
            'products'   => isset($_POST['invoice']['products']) ? $_POST['invoice']['products'] : [],
            'invoice'    => $_POST['invoice']['id']
        ];

        APP::Module('DB')->Insert(
            $this->settings['module_billing_db_connection'], 'billing_invoices_tag', Array(
                'id'          => 'NULL',
                'invoice'     => [$_POST['invoice']['id'], PDO::PARAM_INT],
                'action'      => ['update_invoice', PDO::PARAM_STR],
                'action_data' => [json_encode($data), PDO::PARAM_STR],
                'cr_date'     => 'NOW()'
            )
        );

        APP::Module('Triggers')->Exec('update_invoice', $data);

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode($out);
        exit;
    }
    
    public function APIRemoveInvoice() {
        $out = [
            'status' => 'success',
            'errors' => []
        ];

        if (!APP::Module('DB')->Select(
            $this->settings['module_billing_db_connection'], ['fetchColumn', 0], ['COUNT(id)'], 'billing_invoices', [['id', '=', $_POST['id'], PDO::PARAM_INT]]
        )) {
            $out['status']   = 'error';
            $out['errors'][] = 1;
        }

        if ($out['status'] == 'success') {
            $out['count'] = APP::Module('DB')->Delete(
                $this->settings['module_billing_db_connection'], 'billing_invoices', [['id', '=', $_POST['id'], PDO::PARAM_INT]]
            );

            APP::Module('Triggers')->Exec('remove_invoice', ['id' => $_POST['id'], 'result' => $out['count']]);
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode($out);
        exit;
    }

    public function APIUpdateInvoicesDetails() {
        $out = [
            'status' => 'success',
            'errors' => []
        ];

        if (isset($_POST['lastname'])) {
            APP::Module('DB')->Insert(
                $this->settings['module_billing_db_connection'], 'billing_invoices_details', Array(
                    'id'         => 'NULL',
                    'invoice_id' => [$_POST['invoice_id'], PDO::PARAM_INT],
                    'item'       => ['lastname', PDO::PARAM_STR],
                    'value'      => [$_POST['lastname'], PDO::PARAM_STR]
                )
            );
        }

        if (isset($_POST['firstname'])) {
            APP::Module('DB')->Insert(
                $this->settings['module_billing_db_connection'], 'billing_invoices_details', Array(
                    'id'         => 'NULL',
                    'invoice_id' => [$_POST['invoice_id'], PDO::PARAM_INT],
                    'item'       => ['firstname', PDO::PARAM_STR],
                    'value'      => [$_POST['firstname'], PDO::PARAM_STR]
                )
            );
        }

        if (isset($_POST['tel'])) {
            APP::Module('DB')->Insert(
                $this->settings['module_billing_db_connection'], 'billing_invoices_details', Array(
                    'id'         => 'NULL',
                    'invoice_id' => [$_POST['invoice_id'], PDO::PARAM_INT],
                    'item'       => ['tel', PDO::PARAM_STR],
                    'value'      => [$_POST['tel'], PDO::PARAM_STR]
                )
            );
        }

        if (isset($_POST['email'])) {
            APP::Module('DB')->Insert(
                $this->settings['module_billing_db_connection'], 'billing_invoices_details', Array(
                    'id'         => 'NULL',
                    'invoice_id' => [$_POST['invoice_id'], PDO::PARAM_INT],
                    'item'       => ['email', PDO::PARAM_STR],
                    'value'      => [$_POST['email'], PDO::PARAM_STR]
                )
            );
        }

        if (isset($_POST['comment'])) {
            APP::Module('DB')->Insert(
                $this->settings['module_billing_db_connection'], 'billing_invoices_details', Array(
                    'id'         => 'NULL',
                    'invoice_id' => [$_POST['invoice_id'], PDO::PARAM_INT],
                    'item'       => ['comment', PDO::PARAM_STR],
                    'value'      => [$_POST['comment'], PDO::PARAM_STR]
                )
            );
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode($out);
        exit;
    }

    public function PaymentMake() {
        $invoice_id = APP::Module('Crypt')->Decode(APP::Module('Routing')->get['invoice_id_hash']);
        
        $data['packages']                     = [];
        $data['products']                     = [];
        $data['coupon']['state']              = 'open';
        $data['coupon']['settings']['amount'] = 100;
        $data['invoice_details']              = [
            'lastname'  => '',
            'firstname' => '',
            'email'     => '',
            'tel'       => '',
            'comment'   => ''
        ];

        $data['invoice'] = APP::Module('DB')->Select(
            APP::Module('Billing')->settings['module_billing_db_connection'],
            ['fetch', PDO::FETCH_ASSOC],
            ['users.email', 'billing_invoices.id', 'billing_invoices.amount', 'billing_invoices.state', 'billing_invoices.author', 'billing_invoices.user_id'],
            'billing_invoices',
            [['billing_invoices.id', '=', $invoice_id, PDO::PARAM_STR]],
            [
                'left join/users' => [
                    ['users.id', '=', 'billing_invoices.user_id']
                ]
            ]
        );

        APP::Render('billing/payments/make', 'include', $data);
    }
    
    
    public function APIEAutopayCreateInvoice() {
        $request = json_encode($_POST);

        $sql = APP::Module('DB')->Open($this->settings['module_billing_db_connection'])->prepare('INSERT INTO billing_eautopay_log VALUES (
            NULL, 
            :request, 
            NOW()
        )');
        $sql->bindParam(':request', $request, PDO::PARAM_STR);
        $sql->execute();

        if (((!isset($_POST['status'])) || (!isset($_POST['email'])) || (!isset($_POST['product_price'])))) {
            exit;
        }
        
        if ((int) $_POST['status'] =! 5) {
            exit;
        }
        
        if (isset($_POST['duplicate'])) {
            exit;
        }

        if (!$user_id = APP::Module('DB')->Select(
            APP::Module('Users')->settings['module_users_db_connection'], ['fetchColumn', 0],
            ['id'], 'users',
            [['email', '=', $_POST['email'], PDO::PARAM_STR]]
        )) {
            exit;
        }

        $comment = Array();

        foreach ($_POST as $key => $value) {
            $comment[] = $key. ' - ' . (is_array($value) ? json_encode($value) : $value);
        }

        $message = implode('<br>', $comment);

        if (!APP::Module('DB')->Select(
            APP::Module('Comments')->settings['module_comments_db_connection'], ['fetchColumn', 0],
            ['COUNT(id)'], 'comments_messages',
            [['MD5(message)', '=', md5($message), PDO::PARAM_STR]]
        )) {
            $this->CreateInvoice(
                $user_id, 
                0, 
                [
                    [
                        'id' => 9,
                        'amount' => $_POST['product_price']
                    ]
                ], 
                'success',
                $message
            );
        }
    }
    
    
    public function UpdateExchangeRate() {
        $xml = simplexml_load_file('http://www.cbr.ru/scripts/XML_daily.asp');

        if (count($xml->Valute)) {
            $code = array_reverse($this->conf['currency_code']);
            foreach ($xml->Valute as $item) {
                if (in_array($item->CharCode->__toString(), array_keys($code))) {
                    $curs = (float) $item->Value->__toString() / (int) $item->Nominal->__toString();
                    $value = $curs + $curs * $this->conf['currency_plus'];
                    
                    APP::Module('DB')->Update(
                        $this->settings['module_billing_db_connection'], 'billing_currency', [
                            'value' => $value,
                            'symbol' => $this->conf['currency_code'][$item->CharCode->__toString()]
                        ],
                        [['code', '=', $item->CharCode->__toString(), PDO::PARAM_STR]]
                    );
                }
            }
        }
    }

    public function Sales(){

        $comment_object_type = APP::Module('DB')->Select(APP::Module('Comments')->settings['module_comments_db_connection'], ['fetchColumn', 0], ['id'], 'comments_objects', [['name', '=', "Invoice", PDO::PARAM_STR]]);
        $comment_object_type_user = APP::Module('DB')->Select(APP::Module('Comments')->settings['module_comments_db_connection'], ['fetchColumn', 0], ['id'], 'comments_objects', [['name', '=', "UserAdmin", PDO::PARAM_STR]]);
        
        if (isset($_POST['do'])) {
            switch ($_POST['do']) {
                case 'comments':
                    $out = APP::Module('DB')->Select(
                        APP::Module('Comments')->settings['module_comments_db_connection'], ['fetchAll',PDO::FETCH_ASSOC],
                        ['comments_messages.message','comments_messages.up_date', 'users.email'], 'comments_messages',
                        [['comments_messages.object_type', '=', $comment_object_type_user, PDO::PARAM_INT], ['comments_messages.object_id', '=', $_POST['user'], PDO::PARAM_INT],],['left join/users' => [['users.id','=','comments_messages.user']]]
                    );


                    header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
                    header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
                    header('Content-Type: application/json');

                    echo json_encode($out);
                    exit;
                    break;
                case 'post-comment':

                    APP::Module('DB')->Insert(
                        APP::Module('Comments')->settings['module_comments_db_connection'], 'comments_messages',
                        [
                            'id' => 'NULL',
                            'sub_id' => [0, PDO::PARAM_INT],
                            'user' => [APP::Module('Users')->user['id'], PDO::PARAM_INT],
                            'object_type' => [$comment_object_type_user, PDO::PARAM_INT],
                            'object_id' => [$_POST['user'], PDO::PARAM_INT],
                            'message' => [$_POST['comment'], PDO::PARAM_STR],
                            'url' => [$_SERVER['HTTP_REFERER'], PDO::PARAM_STR],
                            'up_date' => 'NOW()'
                        ]
                    );

                    echo json_encode(Array(
                        'full'  => $_POST['comment']
                    ));
                    exit;
                    break;
                case 'invoices':
                    $out = [];

                    foreach (APP::Module('DB')->Select(
                        APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_ASSOC],
                        ['billing_invoices.*', 'billing_invoices_details.value as comment'], 'billing_invoices',
                        [
                            ['billing_invoices.user_id', '=', $_POST['user'], PDO::PARAM_INT],
                            ['billing_invoices.state', 'IN', ['success', 'processed', 'new'], PDO::PARAM_STR]
                        ],
                        [
                            'left join/billing_invoices_details' => [
                                ['billing_invoices_details.invoice', '=', 'billing_invoices.id'],
                                ['billing_invoices_details.item', '=', '"comment"']
                            ]
                        ], false, false, ['billing_invoices.id', 'desc']
                    ) as $user_invoice) {
                        $user_invoice['hash_id'] = APP::Module('Crypt')->Encode($user_invoice['id']);
                        $invoice_products = APP::Module('DB')->Select(
                            APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_ASSOC],
                            [
                                'billing_invoices_products.id', 
                                'billing_invoices_products.type', 
                                'billing_invoices_products.product',
                                'billing_invoices_products.amount',
                                'billing_invoices_products.cr_date',
                                'billing_products.name',
                                'billing_invoices_products.invoice'
                            ], 
                            'billing_invoices_products',
                            [['billing_invoices_products.invoice', '=', $user_invoice['id'], PDO::PARAM_INT]],
                            [
                                'join/billing_products' => [['billing_products.id', '=', 'billing_invoices_products.product']]
                            ],
                            ['billing_invoices_products.id'],
                            false,
                            ['billing_invoices_products.id', 'desc']
                        );
                        
                        $adm_comment = APP::Module('DB')->Select(
                            APP::Module('Comments')->settings['module_comments_db_connection'], ['fetchAll',PDO::FETCH_ASSOC],
                            ['comments_messages.message','comments_messages.up_date', 'users.email'], 'comments_messages',
                            [['comments_messages.user', '=', $_POST['user'], PDO::PARAM_INT],['comments_messages.object_type', '=', $comment_object_type, PDO::PARAM_INT], ['comments_messages.object_id', '=', $user_invoice['id'], PDO::PARAM_INT]],['left join/users' => [['users.id','=','comments_messages.user']]]
                        );

                        foreach ($invoice_products as $invoice_product) {
                            $out['user_invoices_products'][] = Array(
                                'id'          => $invoice_product['id'],
                                'name'        => $invoice_product['name'],
                                'amount'       => $invoice_product['amount'],
                                'state'       => $user_invoice['state'] == 'success',
                                'invoice'     => $user_invoice['id'],
                                'comment'     => $user_invoice['comment'],
                                'adm_comment' => $adm_comment
                            );
                        }

                        $out['user_invoices'][] = Array(
                            'main'        => $user_invoice,
                            'products'    => $invoice_products,
                            'comment'     => $user_invoice['comment'],
                            'adm_comment' => $adm_comment
                        );
                    }

                    header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
                    header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
                    header('Content-Type: application/json');

                    echo json_encode($out['user_invoices']);
                    exit;
                    break;
            }
        }
            
        ////////////////////////////////////////////////////////////////////////

        $sale = json_decode($this->settings['module_billing_sales_tool'], true);
        $tunnels = [];
        foreach(APP::Module('DB')->Select(
            APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetchAll',PDO::FETCH_ASSOC],
            ['id', 'name'], 'tunnels',
            [['id', 'IN', array_keys($sale), PDO::PARAM_INT], ['state', '=', 'active', PDO::PARAM_STR]]
        ) as $tunnel){
            $tunnels[$tunnel['id']] = $tunnel['name'];
        }

        ////////////////////////////////////////////////////////////////////////

        $uid = APP::Module('Users')->UsersSearch(json_decode($_POST['rules'], true));

        ////////////////////////////////////////////////////////////////////////

        $users = [];

        foreach (APP::Module('DB')->Select(
            APP::Module('Users')->settings['module_users_db_connection'], ['fetchAll',PDO::FETCH_ASSOC],
            ['user', 'value', 'item'], 'users_about',
            [['user', 'IN', $uid, PDO::PARAM_INT], ['item', 'IN', ['firstname', 'tel', 'lastname'], PDO::PARAM_STR]]
        ) as $data) {
            $users[$data['user']][$data['item']] = $data['value'];
        }

        ////////////////////////////////////////////////////////////////////////

        $user_tunnels = [];

        foreach (APP::Module('DB')->Select(
            APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetchAll',PDO::FETCH_ASSOC],
            ['id', 'tunnel_id', 'user_id'], 'tunnels_users',
            [['user_id', 'IN', $uid, PDO::PARAM_INT], ['tunnel_id', 'IN', array_keys($sale), PDO::PARAM_INT], ['state', '=', 'active', PDO::PARAM_STR]]
        ) as $proc) {
            $user_tunnels[$proc['user_id']] = Array($proc['tunnel_id'], $proc['id']);
        }

        $data = [];

        foreach (APP::Module('DB')->Select(
            APP::Module('Users')->settings['module_users_db_connection'], ['fetchAll', PDO::FETCH_ASSOC],
            [
                'users.id', 
                'users.email',
                'count(billing_invoices.id) as inv_cnt',
            ], 
            'users',
            [['users.id', 'IN', $uid, PDO::PARAM_INT], ['users.id', '>', 0, PDO::PARAM_INT]],
            [
                'left join/billing_invoices' => [['billing_invoices.user_id', '=', 'users.id'],['billing_invoices.state', '=', '"success"']]
            ],
            ['users.id'],
            false,
            ['users.id', 'DESC']
        ) as $user) {

            $comment_data = APP::Module('DB')->Select(
                APP::Module('Comments')->settings['module_comments_db_connection'], ['fetch',PDO::FETCH_ASSOC],
                ['comments_messages.message','comments_messages.up_date'], 'comments_messages',
                [['comments_messages.object_id', '=', $user['id'], PDO::PARAM_INT],['comments_messages.object_type', '=', $comment_object_type_user, PDO::PARAM_INT]],
                false, false, false, ['id', 'desc'], [0, 1]
            );

            $inv_pr_cnt = APP::Module('DB')->Select(
                APP::Module('Users')->settings['module_users_db_connection'], ['fetch',PDO::FETCH_ASSOC],
                ['count(id) as inv_cnt'], 'billing_invoices',
                [['user_id', '=', $user['id'], PDO::PARAM_INT],['state', 'IN', ['processed', 'new'], PDO::PARAM_STR]]
            );

            $sale_token = isset($user_tunnels[$user['id']][1]) ? APP::Module('DB')->Select(
                APP::Module('Tunnels')->settings['module_tunnels_db_connection'], ['fetch', PDO::FETCH_COLUMN],
                ['token'], 'tunnels_tags',
                [['user_tunnel_id', '=', $user_tunnels[$user['id']][1], PDO::PARAM_INT],['label_id', '=', 'sendmail', PDO::PARAM_STR]],
                false,false,false, ['id', 'desc'], [0, 1]
            ) : 0;

            $data[] = [
                'id' => $user['id'],
                'email' => $user['email'],
                'inv_cnt' => $user['inv_cnt'],
                'sale_token' => $sale_token,
                'sale' => $sale,
                'comment' => isset($comment_data['message']) ? APP::Module('Utils')->mbCutString($comment_data['message'], 50) . ' (' . $comment_data['up_date'] . ')' : 'Нет',
                'inv'     => $user['inv_cnt'] ? $user['inv_cnt'] : 'Нет',
                'inv_pr'  => $inv_pr_cnt['inv_cnt'] ? $inv_pr_cnt['inv_cnt'] : 'Нет',
                'inv_pr_cnt' => $inv_pr_cnt,
                'comment_data' => $comment_data
            ];

        }
      
        APP::Render('billing/admin/sales', 'include', ['users_data' => $data, 'users' => $users, 'sale' => $sale, 'user_tunnels' => $user_tunnels, 'tunnels' => $tunnels]);
    }
    
}


class ProductsSearch {

    public function id($settings) {
        return APP::Module('DB')->Select(
            APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], ['id'], 'billing_products', [['id', $settings['logic'], $settings['value'], PDO::PARAM_STR]]
        );
    }

    public function name($settings) {
        return APP::Module('DB')->Select(
            APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], ['id'], 'billing_products', [['name', $settings['logic'], $settings['value'], PDO::PARAM_STR]]
        );
    }

    public function amount($settings) {
        return APP::Module('DB')->Select(
            APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], ['id'], 'billing_products', [['amount', $settings['logic'], $settings['value'], PDO::PARAM_STR]]
        );
    }
    
}

class InvoicesSearch {

    public function id($settings) {
        return APP::Module('DB')->Select(
            APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], ['id'], 'billing_invoices', [['id', $settings['logic'], $settings['value'], PDO::PARAM_STR]]
        );
    }

    public function user($settings) {
        return APP::Module('DB')->Select(
            APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], ['id'], 'billing_invoices', [['user_id', $settings['logic'], $settings['value'], PDO::PARAM_STR]]
        );
    }

    public function amount($settings) {
        return APP::Module('DB')->Select(
            APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], ['id'], 'billing_invoices', [['amount', $settings['logic'], $settings['value'], PDO::PARAM_STR]]
        );
    }
    
    public function author($settings) {
        return APP::Module('DB')->Select(
            APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], ['id'], 'billing_invoices', [['author', $settings['logic'], $settings['value'], PDO::PARAM_STR]]
        );
    }
    
    public function state($settings) {
        return APP::Module('DB')->Select(
            APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], ['id'], 'billing_invoices', [['state', $settings['logic'], $settings['value'], PDO::PARAM_STR]]
        );
    }
    
    public function cr_date($settings) {
        return APP::Module('DB')->Select(
            APP::Module('Billing')->settings['module_billing_db_connection'], 
            ['fetchAll', PDO::FETCH_COLUMN], 
            ['id'], 'billing_invoices',
            [
                ['UNIX_TIMESTAMP(cr_date)', 'BETWEEN', $settings['date_from'] . ' AND ' . $settings['date_to'], PDO::PARAM_STR]
            ]
        );
    }
    
}


class ProductsActions {
    
    public function remove($id, $settings) {
        return APP::Module('DB')->Delete(APP::Module('Billing')->settings['module_billing_db_connection'], 'billing_products', [['id', 'IN', $id]]);
    }
    
}

class InvoicesActions {
    
    public function remove($id, $settings) {
        return APP::Module('DB')->Delete(APP::Module('Billing')->settings['module_billing_db_connection'], 'billing_invoices', [['id', 'IN', $id]]);
    }
}

class PaymentsActions {
    
    public function remove($id, $settings) {
        return APP::Module('DB')->Delete(APP::Module('Billing')->settings['module_billing_db_connection'], 'billing_payments', [['id', 'IN', $id]]);
    }
    
}

class PaymentsSearch {
    
    public function invoice($settings) {
        return APP::Module('DB')->Select(
            APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], ['id'], 'billing_payments', [['invoice_id', $settings['logic'], $settings['value'], PDO::PARAM_INT]]
        );
    }
    
    public function method($settings) {
        return APP::Module('DB')->Select(
            APP::Module('Billing')->settings['module_billing_db_connection'], ['fetchAll', PDO::FETCH_COLUMN], ['id'], 'billing_payments', [['method', $settings['logic'], $settings['value'], PDO::PARAM_STR]]
        );
    }
    
}
