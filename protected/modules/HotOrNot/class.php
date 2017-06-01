<?
class HotOrNot {

    public $settings;
    
    private $people_search;
    private $people_actions;
    
    private $story_search;
    private $story_actions;

    function __construct($conf) {
        foreach ($conf['routes'] as $route) APP::Module('Routing')->Add($route[0], $route[1], $route[2]);
    }
    
    public function Init() {
        $this->settings = APP::Module('Registry')->Get([
            'module_hotornot_db_connection'
        ]);
        
        $this->people_search = new PeopleSearch();
        $this->people_actions = new PeopleActions();
        
        $this->story_search = new StorySearch();
        $this->story_actions = new StoryActions();
    }
    
    public function Admin() {
        return APP::Render('hotornot/admin/nav', 'content');
    }

    
    public function PeopleSearch($rules) {
        $out = Array();

        foreach ((array) $rules['rules'] as $rule) {
            $out[] = array_flip((array) $this->people_search->{$rule['method']}($rule['settings']));
        }
        
        if (array_key_exists('children', (array) $rules)) {
            $out[] = array_flip((array) $this->PeopleSearch($rules['children']));
        }
        
        if (count($out) > 1) {
            switch ($rules['logic']) {
                case 'intersect': return array_keys((array) call_user_func_array('array_intersect_key', $out)); break;
                case 'merge': return array_keys((array) call_user_func_array('array_replace', $out)); break;
            }
        } else {
            return array_keys($out[0]);
        }
    }
    
    public function StorySearch($rules) {
        $out = Array();

        foreach ((array) $rules['rules'] as $rule) {
            $out[] = array_flip((array) $this->story_search->{$rule['method']}($rule['settings']));
        }
        
        if (array_key_exists('children', (array) $rules)) {
            $out[] = array_flip((array) $this->StorySearch($rules['children']));
        }
        
        if (count($out) > 1) {
            switch ($rules['logic']) {
                case 'intersect': return array_keys((array) call_user_func_array('array_intersect_key', $out)); break;
                case 'merge': return array_keys((array) call_user_func_array('array_replace', $out)); break;
            }
        } else {
            return array_keys($out[0]);
        }
    }
    
    
    public function ManagePeople() {
        APP::Render('hotornot/admin/people/index');
    }
    
    public function AddPeople() {
        APP::Render('hotornot/admin/people/add');
    }
    
    public function EditPeople() {
        $people_id = APP::Module('Crypt')->Decode(APP::Module('Routing')->get['people_id_hash']);
        
        APP::Render(
            'hotornot/admin/people/edit', 'include', 
            [
                'people' => APP::Module('DB')->Select(
                    $this->settings['module_hotornot_db_connection'], ['fetch', PDO::FETCH_ASSOC], 
                    ['email', 'name', 'city', 'image', 'description'], 'hotornot_people',
                    [['id', '=', $people_id, PDO::PARAM_INT]]
                ),
            ]
        );
    }
    
    
    public function ManageStory() {
        APP::Render('hotornot/admin/story/index');
    }
    
    public function AddStory() {
        APP::Render('hotornot/admin/story/add');
    }
    
    public function EditStory() {
        $story_id = APP::Module('Crypt')->Decode(APP::Module('Routing')->get['story_id_hash']);
        
        APP::Render(
            'hotornot/admin/story/edit', 'include', 
            [
                'story' => APP::Module('DB')->Select(
                    $this->settings['module_hotornot_db_connection'], ['fetch', PDO::FETCH_ASSOC], 
                    ['email', 'name', 'city', 'caption', 'story', 'description'], 'hotornot_story',
                    [['id', '=', $story_id, PDO::PARAM_INT]]
                ),
            ]
        );
    }
    
    
    public function Poll() {
        APP::Render('hotornot/poll');
    }
    
    public function PollStory() {
        APP::Render('hotornot/story/poll');
    }
    
    public function PollBeta() {
        APP::Render('hotornot/poll_beta');
    }
    
    
    public function Top() {
        $out = [];
        $users = [];
        
        foreach (APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetchAll', PDO::FETCH_ASSOC], 
            ['id'], 'hotornot_people'
        ) as $value) {
            $out[$value['id']] = 0;
        }
        
        foreach (APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetchAll', PDO::FETCH_ASSOC], 
            ['user', 'people', 'rate'], 'hotornot_poll'
        ) as $value) {
            $users[$value['people']][$value['rate']][] = $value['user'];
        }
        
        foreach ($users as $key => $value) {
            $hot = (int) isset($value['hot']) ? count($value['hot']) : 0;
            $not = (int) isset($value['not']) ? count($value['not']) : 0;
            
            $out[$key] = $hot - $not;
        }
        
        arsort($out);
        
        foreach ($out as $key => $value) {
            $out[$key] = [
                'people' => $key,
                'hot' => (int) isset($users[$key]['hot']) ? count($users[$key]['hot']) : 0,
                'not' => (int) isset($users[$key]['not']) ? count($users[$key]['not']) : 0,
                'description' => APP::Module('DB')->Select(
                    $this->settings['module_hotornot_db_connection'], ['fetchColumn', 0], 
                    ['description'], 'hotornot_people',
                    [['id', '=', $key, PDO::PARAM_INT]]
                ), 
                'total' => $value
            ];
        }
        
        if (!isset(APP::Module('Routing')->get['all'])) {
            array_splice($out, 10);
        } else {
            $out = array_values($out);
        }

        APP::Render('hotornot/top', 'include', $out);
    }
    
    public function TopStory() {
        $out = [];
        $users = [];
        
        foreach (APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetchAll', PDO::FETCH_ASSOC], 
            ['id'], 'hotornot_story'
        ) as $value) {
            $out[$value['id']] = 0;
        }
        
        foreach (APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetchAll', PDO::FETCH_ASSOC], 
            ['user', 'story', 'rate'], 'hotornot_story_poll'
        ) as $value) {
            $users[$value['story']][$value['rate']][] = $value['user'];
        }
        
        foreach ($users as $key => $value) {
            $hot = (int) isset($value['hot']) ? count($value['hot']) : 0;
            $not = (int) isset($value['not']) ? count($value['not']) : 0;
            
            $out[$key] = $hot - $not;
        }
        
        arsort($out);
        
        foreach ($out as $key => $value) {
            $out[$key] = [
                'story_id' => $key,
                'hot' => (int) isset($users[$key]['hot']) ? count($users[$key]['hot']) : 0,
                'not' => (int) isset($users[$key]['not']) ? count($users[$key]['not']) : 0,
                'story' => APP::Module('DB')->Select(
                    $this->settings['module_hotornot_db_connection'], ['fetch', PDO::FETCH_ASSOC], 
                    ['caption', 'story AS text'], 'hotornot_story',
                    [['id', '=', $key, PDO::PARAM_INT]]
                ), 
                'total' => $value
            ];
        }
        
        if (!isset(APP::Module('Routing')->get['all'])) {
            array_splice($out, 10);
        } else {
            $out = array_values($out);
        }

        APP::Render('hotornot/story/top', 'include', $out);
    }
    
    
    public function Settings() {
        APP::Render('hotornot/admin/settings');
    }
    
    public function PeopleImage() {
        $image = explode(',', APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], ['fetchColumn', 0], 
            ['image'], 'hotornot_people',
            [['id', '=', APP::Module('Routing')->get['id'], PDO::PARAM_INT]]
        ));

        $image_src = imagecreatefromstring(base64_decode($image[1]));
        
        $image_size = [
            imagesx($image_src),
            imagesy($image_src)
        ];
        
        $new_size = explode('x', APP::Module('Routing')->get['size']);
        
        if (count(array_diff($image_size, $new_size))) {
            $scale = min(
                $new_size[0] / $image_size[0],
                $new_size[1] / $image_size[1]
            );

            $image_size = [
                round($image_size[0] * $scale),
                round($image_size[1] * $scale)
            ];
        }

        $out_image = imagecreatetruecolor($image_size[0], $image_size[1]);
        $image_mime = explode(';', (explode(':', $image[0])[1]))[0];

        switch ($image_mime) {
            case 'image/jpg':
            case 'image/jpeg':
                $write_image = 'imagejpeg';
                $image_quality = 75;
                break;
            case 'image/gif':
                imagecolortransparent($out_image, imagecolorallocate($out_image, 0, 0, 0));
                $write_image = 'imagegif';
                $image_quality = null;
                break;
            case 'image/png':
                imagecolortransparent($out_image, imagecolorallocate($out_image, 0, 0, 0));
                imagealphablending($out_image, false);
                imagesavealpha($out_image, true);
                $write_image = 'imagepng';
                $image_quality = 9;
                break;
        }

        header('Pragma: public');
        header("Cache-Control: max-age=604800");
        header("Expires: " . date(DATE_RFC822,strtotime("1 week")));
        header("Content-type: " . $image_mime);
        header("Accept-Ranges: bytes");

        imagecopyresampled(
            $out_image,
            $image_src,
            0, 0, 0, 0,
            $image_size[0],
            $image_size[1],
            imagesx($image_src),
            imagesy($image_src)
        );
        
        ob_start();
        $write_image($out_image, NULL, $image_quality);
        $size = ob_get_length();
        header("Content-Length: " . $size);
        ob_end_flush();

        imagedestroy($image_src);
        imagedestroy($out_image);
    }
    
    public function Users() {
        echo APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], ['fetchColumn', 0], 
            ['COUNT(DISTINCT user)'], 'hotornot_poll'
        );
    }
    
    public function PublicRedirect() {
        header('Location: ' . APP::Module('Routing')->root . 'public/modules/hotornot/images' . APP::Module('Routing')->get['id']);
    }
    
    
    public function APISearchPeople() {
        $request = json_decode(file_get_contents('php://input'), true);
        $out = $this->PeopleSearch(json_decode($request['search'], 1));
        $rows = [];

        foreach (APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], ['fetchAll', PDO::FETCH_ASSOC], 
            ['id', 'email', 'name', 'city', 'up_date'], 'hotornot_people',
            [['id', 'IN', $out, PDO::PARAM_INT]], 
            false, false, false,
            [$request['sort_by'], $request['sort_direction']],
            $request['rows'] === -1 ? false : [($request['current'] - 1) * $request['rows'], $request['rows']]
        ) as $row) {
            $row['people_id_token'] = APP::Module('Crypt')->Encode($row['id']);
            array_push($rows, $row);
        }
        
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');
        
        echo json_encode([
            'current' => $request['current'],
            'rowCount' => $request['rows'],
            'rows' => $rows,
            'total' => count($out)
        ]);
        exit;
    }
    
    public function APISearchPeopleAction() {
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode($this->people_actions->{$_POST['action']}($this->PeopleSearch(json_decode($_POST['rules'], 1)), isset($_POST['settings']) ? $_POST['settings'] : false));
        exit;
    }
    
    public function APIAddPeople() {
        $out = [
            'status' => 'success',
            'errors' => []
        ];
        
        if ($out['status'] == 'success') {
            $image_type = explode('/', explode(';', $_POST['image'])[0]);
            
            $out['people_id'] = APP::Module('DB')->Insert(
                $this->settings['module_hotornot_db_connection'], 'hotornot_people',
                Array(
                    'id' => 'NULL',
                    'email' => [$_POST['email'], PDO::PARAM_STR],
                    'name' => [$_POST['name'], PDO::PARAM_STR],
                    'city' => [$_POST['city'], PDO::PARAM_STR],
                    'image_type' => [$image_type[1], PDO::PARAM_STR],
                    'image' => [$_POST['image'], PDO::PARAM_STR],
                    'description' => [$_POST['description'], PDO::PARAM_STR],
                    'up_date' => 'NOW()'
                )
            );
            
            APP::Module('Triggers')->Exec('add_people', [
                'id' => $out['people_id'],
                'email' => $_POST['email'],
                'name' => $_POST['name'],
                'city' => $_POST['city'],
                'image_type' => $image_type[1],
                'image' => $_POST['image'],
                'description' => $_POST['description']
            ]);
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');
        
        echo json_encode($out);
        exit;
    }
    
    public function APIRemovePeople() {
        $out = [
            'status' => 'success',
            'errors' => []
        ];

        if (!APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], ['fetchColumn', 0], 
            ['COUNT(id)'], 'hotornot_people',
            [['id', '=', $_POST['id'], PDO::PARAM_INT]]
        )) {
            $out['status'] = 'error';
            $out['errors'][] = 1;
        }
        
        if ($out['status'] == 'success') {
            $out['count'] = APP::Module('DB')->Delete(
                $this->settings['module_hotornot_db_connection'], 'hotornot_people',
                [['id', '=', $_POST['id'], PDO::PARAM_INT]]
            );
            
            APP::Module('Triggers')->Exec('remove_people', ['id' => $_POST['id'], 'result' => $out['count']]);
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');
        
        echo json_encode($out);
        exit;
    }
    
    public function APIUpdatePeople() {
        $out = [
            'status' => 'success',
            'errors' => []
        ];
        
        $people_id = APP::Module('Crypt')->Decode($_POST['id']);

        if (!APP::Module('DB')->Select($this->settings['module_hotornot_db_connection'], ['fetchColumn', 0], ['COUNT(id)'], 'hotornot_people', [['id', '=', $people_id, PDO::PARAM_INT]])) {
            $out['status'] = 'error';
            $out['errors'][] = 1;
        }

        if ($out['status'] == 'success') {
            $image_type = explode('/', explode(';', $_POST['image'])[0]);
            
            APP::Module('DB')->Update($this->settings['module_hotornot_db_connection'], 'hotornot_people', [
                'email' => $_POST['email'],
                'name' => $_POST['name'],
                'city' => $_POST['city'],
                'image_type' => $image_type[1],
                'image' => $_POST['image'],
                'description' => $_POST['description']
            ], [
                ['id', '=', $people_id, PDO::PARAM_INT]
            ]);

            APP::Module('Triggers')->Exec('update_people', [
                'id' => $people_id,
                'email' => $_POST['email'],
                'name' => $_POST['name'],
                'city' => $_POST['city'],
                'image_type' => $image_type[1],
                'image' => $_POST['image'],
                'description' => $_POST['description']
            ]);
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');
        
        echo json_encode($out);
        exit;
    }
    
    
    public function APISearchStory() {
        $request = json_decode(file_get_contents('php://input'), true);
        $out = $this->StorySearch(json_decode($request['search'], 1));
        $rows = [];

        foreach (APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], ['fetchAll', PDO::FETCH_ASSOC], 
            ['id', 'email', 'name', 'city', 'up_date'], 'hotornot_story',
            [['id', 'IN', $out, PDO::PARAM_INT]], 
            false, false, false,
            [$request['sort_by'], $request['sort_direction']],
            $request['rows'] === -1 ? false : [($request['current'] - 1) * $request['rows'], $request['rows']]
        ) as $row) {
            $row['story_id_token'] = APP::Module('Crypt')->Encode($row['id']);
            array_push($rows, $row);
        }
        
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');
        
        echo json_encode([
            'current' => $request['current'],
            'rowCount' => $request['rows'],
            'rows' => $rows,
            'total' => count($out)
        ]);
        exit;
    }
    
    public function APISearchStoryAction() {
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');

        echo json_encode($this->story_actions->{$_POST['action']}($this->StorySearch(json_decode($_POST['rules'], 1)), isset($_POST['settings']) ? $_POST['settings'] : false));
        exit;
    }
    
    public function APIAddStory() {
        $out = [
            'status' => 'success',
            'errors' => []
        ];
        
        if ($out['status'] == 'success') {
            $out['story_id'] = APP::Module('DB')->Insert(
                $this->settings['module_hotornot_db_connection'], 'hotornot_story',
                Array(
                    'id' => 'NULL',
                    'email' => [$_POST['email'], PDO::PARAM_STR],
                    'name' => [$_POST['name'], PDO::PARAM_STR],
                    'city' => [$_POST['city'], PDO::PARAM_STR],
                    'caption' => [$_POST['caption'], PDO::PARAM_STR],
                    'story' => [$_POST['story'], PDO::PARAM_STR],
                    'description' => [$_POST['description'], PDO::PARAM_STR],
                    'up_date' => 'NOW()'
                )
            );
            
            APP::Module('Triggers')->Exec('add_story', [
                'id' => $out['story_id'],
                'email' => $_POST['email'],
                'name' => $_POST['name'],
                'city' => $_POST['city'],
                'caption' => $_POST['caption'],
                'story' => $_POST['story'],
                'description' => $_POST['description']
            ]);
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');
        
        echo json_encode($out);
        exit;
    }
    
    public function APIRemoveStory() {
        $out = [
            'status' => 'success',
            'errors' => []
        ];

        if (!APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], ['fetchColumn', 0], 
            ['COUNT(id)'], 'hotornot_story',
            [['id', '=', $_POST['id'], PDO::PARAM_INT]]
        )) {
            $out['status'] = 'error';
            $out['errors'][] = 1;
        }
        
        if ($out['status'] == 'success') {
            $out['count'] = APP::Module('DB')->Delete(
                $this->settings['module_hotornot_db_connection'], 'hotornot_story',
                [['id', '=', $_POST['id'], PDO::PARAM_INT]]
            );
            
            APP::Module('Triggers')->Exec('remove_story', ['id' => $_POST['id'], 'result' => $out['count']]);
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');
        
        echo json_encode($out);
        exit;
    }
    
    public function APIUpdateStory() {
        $out = [
            'status' => 'success',
            'errors' => []
        ];
        
        $story_id = APP::Module('Crypt')->Decode($_POST['id']);

        if (!APP::Module('DB')->Select($this->settings['module_hotornot_db_connection'], ['fetchColumn', 0], ['COUNT(id)'], 'hotornot_story', [['id', '=', $story_id, PDO::PARAM_INT]])) {
            $out['status'] = 'error';
            $out['errors'][] = 1;
        }

        if ($out['status'] == 'success') {
            APP::Module('DB')->Update($this->settings['module_hotornot_db_connection'], 'hotornot_story', [
                'email' => $_POST['email'],
                'name' => $_POST['name'],
                'city' => $_POST['city'],
                'caption' => $_POST['caption'],
                'story' => $_POST['story'],
                'description' => $_POST['description']
            ], [
                ['id', '=', $story_id, PDO::PARAM_INT]
            ]);

            APP::Module('Triggers')->Exec('update_story', [
                'id' => $story_id,
                'email' => $_POST['email'],
                'name' => $_POST['name'],
                'city' => $_POST['city'],
                'caption' => $_POST['caption'],
                'story' => $_POST['story'],
                'description' => $_POST['description']
            ]);
        }

        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');
        
        echo json_encode($out);
        exit;
    }
    
    
    public function APIUpdateSettings() {
        APP::Module('Registry')->Update(['value' => $_POST['module_hotornot_db_connection']], [['item', '=', 'module_hotornot_db_connection', PDO::PARAM_STR]]);

        APP::Module('Triggers')->Exec('update_hotornot_settings', [
            'db_connection' => $_POST['module_hotornot_db_connection']
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
    
    public function APIGetPeopleBeta() {
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');
        
        $out = [];
        $users = [];

        foreach (APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetchAll', PDO::FETCH_ASSOC], 
            ['id'], 'hotornot_people'
        ) as $value) {
            $out[$value['id']] = 0;
        }

        foreach (APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetchAll', PDO::FETCH_ASSOC], 
            ['user', 'people', 'rate'], 'hotornot_poll'
        ) as $value) {
            $users[$value['people']][$value['rate']][] = $value['user'];
        }

        foreach ($users as $key => $value) {
            $hot = (int) isset($value['hot']) ? count($value['hot']) : 0;
            $not = (int) isset($value['not']) ? count($value['not']) : 0;

            $out[$key] = $hot - $not;
        }

        arsort($out);
        $parts = array_chunk(array_keys($out), count($out) / 3);
        
        $exclude_people = APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetchAll', PDO::FETCH_COLUMN], 
            ['people'], 'hotornot_poll',
            [['user', '=', APP::Module('Users')->user['id'], PDO::PARAM_INT]]
        );

        $target_user = $exclude_people ? APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetch', PDO::FETCH_ASSOC], 
            ['id', 'name', 'city', 'image', 'description'], 'hotornot_people',
            [['id', 'NOT IN', $exclude_people, PDO::PARAM_INT]],
            false, false, false,
            ['RAND()'],
            [1]
        ) : APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetch', PDO::FETCH_ASSOC], 
            ['id', 'name', 'city', 'image', 'description'], 'hotornot_people',
            [['id', 'IN', $parts[0], PDO::PARAM_INT]],
            false, false, false,
            ['RAND()'],
            [1]
        );

        if (APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetch', PDO::FETCH_COLUMN], 
            ['COUNT(id)'], 'hotornot_people',
            [['id', 'NOT IN', $exclude_people, PDO::PARAM_INT]]
        ) > 1) {
            foreach ($parts as $index => $part_users) {
                if (array_search($target_user['id'], $part_users) !== false) {
                    $exclude_people[] = $target_user['id'];
                    $target_part_users = array_diff($part_users, $exclude_people);

                    if (count($target_part_users)) {
                        shuffle($target_part_users);
                        $second_user = $target_part_users[0];
                    } else {
                        $second_user = APP::Module('DB')->Select(
                            $this->settings['module_hotornot_db_connection'], 
                            ['fetch', PDO::FETCH_COLUMN], 
                            ['id'], 'hotornot_people',
                            [['id', 'NOT IN', $exclude_people, PDO::PARAM_INT]],
                            false, false, false,
                            ['RAND()'],
                            [1]
                        );
                    }
                }
            }

            echo json_encode([
                $target_user,
                APP::Module('DB')->Select(
                    $this->settings['module_hotornot_db_connection'], 
                    ['fetch', PDO::FETCH_ASSOC], 
                    ['id', 'name', 'city', 'image', 'description'], 'hotornot_people',
                    [['id', '=', $second_user, PDO::PARAM_INT]],
                    false, false, false,
                    ['RAND()'],
                    [1]
                )
            ]);
        } else {
            echo json_encode([]);
        }
    }
    
    
    public function APIGetPeople() {
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');
        
        $out = [];
        $users = [];

        foreach (APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetchAll', PDO::FETCH_ASSOC], 
            ['id'], 'hotornot_people'
        ) as $value) {
            $out[$value['id']] = 0;
        }

        foreach (APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetchAll', PDO::FETCH_ASSOC], 
            ['user', 'people', 'rate'], 'hotornot_poll'
        ) as $value) {
            $users[$value['people']][$value['rate']][] = $value['user'];
        }

        foreach ($users as $key => $value) {
            $hot = (int) isset($value['hot']) ? count($value['hot']) : 0;
            $not = (int) isset($value['not']) ? count($value['not']) : 0;

            $out[$key] = $hot - $not;
        }

        arsort($out);
        $parts = array_chunk(array_keys($out), count($out) / 3);
        
        $exclude_people = APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetchAll', PDO::FETCH_COLUMN], 
            ['people'], 'hotornot_poll',
            [['user', '=', APP::Module('Users')->user['id'], PDO::PARAM_INT]]
        );

        $target_user = $exclude_people ? APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetch', PDO::FETCH_ASSOC], 
            ['id', 'name', 'city', 'image', 'description'], 'hotornot_people',
            [['id', 'NOT IN', $exclude_people, PDO::PARAM_INT]],
            false, false, false,
            ['RAND()'],
            [1]
        ) : APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetch', PDO::FETCH_ASSOC], 
            ['id', 'name', 'city', 'image', 'description'], 'hotornot_people',
            [['id', 'IN', $parts[0], PDO::PARAM_INT]],
            false, false, false,
            ['RAND()'],
            [1]
        );

        if (APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetch', PDO::FETCH_COLUMN], 
            ['COUNT(id)'], 'hotornot_people',
            [['id', 'NOT IN', $exclude_people, PDO::PARAM_INT]]
        ) > 1) {
            foreach ($parts as $index => $part_users) {
                if (array_search($target_user['id'], $part_users) !== false) {
                    $exclude_people[] = $target_user['id'];
                    $target_part_users = array_diff($part_users, $exclude_people);

                    if (count($target_part_users)) {
                        shuffle($target_part_users);
                        $second_user = $target_part_users[0];
                    } else {
                        $second_user = APP::Module('DB')->Select(
                            $this->settings['module_hotornot_db_connection'], 
                            ['fetch', PDO::FETCH_COLUMN], 
                            ['id'], 'hotornot_people',
                            [['id', 'NOT IN', $exclude_people, PDO::PARAM_INT]],
                            false, false, false,
                            ['RAND()'],
                            [1]
                        );
                    }
                }
            }

            echo json_encode([
                $target_user,
                APP::Module('DB')->Select(
                    $this->settings['module_hotornot_db_connection'], 
                    ['fetch', PDO::FETCH_ASSOC], 
                    ['id', 'name', 'city', 'image', 'description'], 'hotornot_people',
                    [['id', '=', $second_user, PDO::PARAM_INT]],
                    false, false, false,
                    ['RAND()'],
                    [1]
                )
            ]);
        } else {
            echo json_encode([]);
        }
    }

    public function APIRatePeople() {
        $hot = APP::Module('DB')->Insert(
            $this->settings['module_hotornot_db_connection'], 'hotornot_poll',
            Array(
                'id' => 'NULL',
                'user' => [APP::Module('Users')->user['id'], PDO::PARAM_INT],
                'people' => [$_POST['hot'], PDO::PARAM_INT],
                'rate' => ['hot', PDO::PARAM_STR],
                'up_date' => 'NOW()'
            )
        );
        
        $not = APP::Module('DB')->Insert(
            $this->settings['module_hotornot_db_connection'], 'hotornot_poll',
            Array(
                'id' => 'NULL',
                'user' => [APP::Module('Users')->user['id'], PDO::PARAM_INT],
                'people' => [$_POST['not'], PDO::PARAM_INT],
                'rate' => ['not', PDO::PARAM_STR],
                'up_date' => 'NOW()'
            )
        );
        
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');
        
        echo json_encode([
            'hot' => $hot,
            'not' => $not
        ]);
    }
    
    
    public function APIGetStory() {
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');
        
        $out = [];
        $users = [];

        foreach (APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetchAll', PDO::FETCH_ASSOC], 
            ['id'], 'hotornot_story'
        ) as $value) {
            $out[$value['id']] = 0;
        }

        foreach (APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetchAll', PDO::FETCH_ASSOC], 
            ['user', 'story', 'rate'], 'hotornot_story_poll'
        ) as $value) {
            $users[$value['story']][$value['rate']][] = $value['user'];
        }

        foreach ($users as $key => $value) {
            $hot = (int) isset($value['hot']) ? count($value['hot']) : 0;
            $not = (int) isset($value['not']) ? count($value['not']) : 0;

            $out[$key] = $hot - $not;
        }

        arsort($out);
        $parts = array_chunk(array_keys($out), count($out) / 3);
        
        $exclude_story = APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetchAll', PDO::FETCH_COLUMN], 
            ['story'], 'hotornot_story_poll',
            [['user', '=', APP::Module('Users')->user['id'], PDO::PARAM_INT]]
        );

        $target_user = $exclude_story ? APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetch', PDO::FETCH_ASSOC], 
            ['id', 'name', 'city', 'caption', 'story'], 'hotornot_story',
            [['id', 'NOT IN', $exclude_story, PDO::PARAM_INT]],
            false, false, false,
            ['RAND()'],
            [1]
        ) : APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetch', PDO::FETCH_ASSOC], 
            ['id', 'name', 'city', 'caption', 'story'], 'hotornot_story',
            [['id', 'IN', $parts[0], PDO::PARAM_INT]],
            false, false, false,
            ['RAND()'],
            [1]
        );

        if (APP::Module('DB')->Select(
            $this->settings['module_hotornot_db_connection'], 
            ['fetch', PDO::FETCH_COLUMN], 
            ['COUNT(id)'], 'hotornot_story',
            [['id', 'NOT IN', $exclude_story, PDO::PARAM_INT]]
        ) > 1) {
            foreach ($parts as $index => $part_users) {
                if (array_search($target_user['id'], $part_users) !== false) {
                    $exclude_story[] = $target_user['id'];
                    $target_part_users = array_diff($part_users, $exclude_story);

                    if (count($target_part_users)) {
                        shuffle($target_part_users);
                        $second_user = $target_part_users[0];
                    } else {
                        $second_user = APP::Module('DB')->Select(
                            $this->settings['module_hotornot_db_connection'], 
                            ['fetch', PDO::FETCH_COLUMN], 
                            ['id'], 'hotornot_story',
                            [['id', 'NOT IN', $exclude_story, PDO::PARAM_INT]],
                            false, false, false,
                            ['RAND()'],
                            [1]
                        );
                    }
                }
            }

            echo json_encode([
                $target_user,
                APP::Module('DB')->Select(
                    $this->settings['module_hotornot_db_connection'], 
                    ['fetch', PDO::FETCH_ASSOC], 
                    ['id', 'name', 'city', 'caption', 'story'], 'hotornot_story',
                    [['id', '=', $second_user, PDO::PARAM_INT]],
                    false, false, false,
                    ['RAND()'],
                    [1]
                )
            ]);
        } else {
            echo json_encode([]);
        }
    }

    public function APIRateStory() {
        $hot = APP::Module('DB')->Insert(
            $this->settings['module_hotornot_db_connection'], 'hotornot_story_poll',
            Array(
                'id' => 'NULL',
                'user' => [APP::Module('Users')->user['id'], PDO::PARAM_INT],
                'story' => [$_POST['hot'], PDO::PARAM_INT],
                'rate' => ['hot', PDO::PARAM_STR],
                'up_date' => 'NOW()'
            )
        );
        
        $not = APP::Module('DB')->Insert(
            $this->settings['module_hotornot_db_connection'], 'hotornot_story_poll',
            Array(
                'id' => 'NULL',
                'user' => [APP::Module('Users')->user['id'], PDO::PARAM_INT],
                'story' => [$_POST['not'], PDO::PARAM_INT],
                'rate' => ['not', PDO::PARAM_STR],
                'up_date' => 'NOW()'
            )
        );
        
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
        header('Access-Control-Allow-Origin: ' . APP::$conf['location'][1]);
        header('Content-Type: application/json');
        
        echo json_encode([
            'hot' => $hot,
            'not' => $not
        ]);
    }
    
}


class PeopleSearch {

    public function name($settings) {
        return APP::Module('DB')->Select(
            APP::Module('HotOrNot')->settings['module_hotornot_db_connection'], 
            ['fetchAll', PDO::FETCH_COLUMN], 
            ['id'], 'hotornot_people',
            [['name', $settings['logic'], $settings['value'], PDO::PARAM_STR]]
        );
    }

}

class PeopleActions {

    public function remove($id, $settings) {
        return APP::Module('DB')->Delete(APP::Module('HotOrNot')->settings['module_hotornot_db_connection'], 'hotornot_people', [['id', 'IN', $id]]);
    }
    
}


class StorySearch {

    public function name($settings) {
        return APP::Module('DB')->Select(
            APP::Module('HotOrNot')->settings['module_hotornot_db_connection'], 
            ['fetchAll', PDO::FETCH_COLUMN], 
            ['id'], 'hotornot_story',
            [['name', $settings['logic'], $settings['value'], PDO::PARAM_STR]]
        );
    }

}

class StoryActions {

    public function remove($id, $settings) {
        return APP::Module('DB')->Delete(APP::Module('HotOrNot')->settings['module_hotornot_db_connection'], 'hotornot_story', [['id', 'IN', $id]]);
    }
    
}