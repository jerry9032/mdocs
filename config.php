<?php
return array(
    'site_name' => 'ksarch',
    'short_name' => 'ksarch',
    'plugins' => array('identify', 'git', 'search', 'specials', 'filestat'),
    #    'disable_cache' => true,
    'vhost' => array(
        'man.baidu.com' => array(
            'doc_root'   => '_doc/man',
            'cache_root' => '_cache/man'
        ),
        'ksarch-store.baidu.com' => array(
            'doc_root'   => '_doc/store',
            'cache_root' => '_cache/store',
        ),
    ),
);
