<?
return [
    'routes' => [
        ['hotornot(\?.*)?',                                        'HotOrNot', 'Poll'],
        ['hotornot\/top(\?.*)?',                                   'HotOrNot', 'Top'],
        
        ['hotornot\/story(\?.*)?',                                 'HotOrNot', 'PollStory'],
        ['hotornot\/story\/top(\?.*)?',                            'HotOrNot', 'TopStory'],
        
        //['hotornot\/beta(\?.*)?',                                  'HotOrNot', 'PollBeta'],
        //['hotornot\/users(\?.*)?',                                 'HotOrNot', 'Users'],
        ['hotornot\/images\/(?P<id>[0-9]+)\/(?P<size>[0-9x]+)',    'HotOrNot', 'PeopleImage'],
        ['lights\/image(?P<id>.*)',                                'HotOrNot', 'PublicRedirect'],

        ['admin\/hotornot\/people(\?.*)?',                         'HotOrNot', 'ManagePeople'],
        ['admin\/hotornot\/people\/add(\?.*)?',                    'HotOrNot', 'AddPeople'],
        ['admin\/hotornot\/people\/edit\/(?P<people_id_hash>.*)',  'HotOrNot', 'EditPeople'],
        
        ['admin\/hotornot\/story(\?.*)?',                          'HotOrNot', 'ManageStory'],
        ['admin\/hotornot\/story\/add(\?.*)?',                     'HotOrNot', 'AddStory'],
        ['admin\/hotornot\/story\/edit\/(?P<story_id_hash>.*)',    'HotOrNot', 'EditStory'],
        
        ['admin\/hotornot\/settings(\?.*)?',                       'HotOrNot', 'Settings'],

        // API
        ['hotornot\/people\/api\/get\.json(\?.*)?',                'HotOrNot', 'APIGetPeople'],
        ['hotornot\/people\/api\/rate\.json(\?.*)?',               'HotOrNot', 'APIRatePeople'],
        
        ['hotornot\/story\/api\/get\.json(\?.*)?',                 'HotOrNot', 'APIGetStory'],
        ['hotornot\/story\/api\/rate\.json(\?.*)?',                'HotOrNot', 'APIRateStory'],
        //['hotornot\/people\/api\/get_beta\.json(\?.*)?',           'HotOrNot', 'APIGetPeopleBeta'],

        ['admin\/hotornot\/people\/api\/search\.json(\?.*)?',      'HotOrNot', 'APISearchPeople'],
        ['admin\/hotornot\/people\/api\/action\.json(\?.*)?',      'HotOrNot', 'APISearchPeopleAction'],
        ['admin\/hotornot\/people\/api\/add\.json(\?.*)?',         'HotOrNot', 'APIAddPeople'],
        ['admin\/hotornot\/people\/api\/remove\.json(\?.*)?',      'HotOrNot', 'APIRemovePeople'],
        ['admin\/hotornot\/people\/api\/update\.json(\?.*)?',      'HotOrNot', 'APIUpdatePeople'],
        
        ['admin\/hotornot\/story\/api\/search\.json(\?.*)?',       'HotOrNot', 'APISearchStory'],
        ['admin\/hotornot\/story\/api\/action\.json(\?.*)?',       'HotOrNot', 'APISearchStoryAction'],
        ['admin\/hotornot\/story\/api\/add\.json(\?.*)?',          'HotOrNot', 'APIAddStory'],
        ['admin\/hotornot\/story\/api\/remove\.json(\?.*)?',       'HotOrNot', 'APIRemoveStory'],
        ['admin\/hotornot\/story\/api\/update\.json(\?.*)?',       'HotOrNot', 'APIUpdateStory'],
        
        ['admin\/hotornot\/api\/settings\/update\.json(\?.*)?',    'HotOrNot', 'APIUpdateSettings'],
    ]
];