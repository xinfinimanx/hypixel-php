<?php

/**
 * HypixelPHP
 *
 * @author Plancke
 * @version 1.0.0
 * @link  http://plancke.nl
 *
 */
class HypixelPHP
{
    private $options;

    public function  __construct($input = array())
    {
        $this->options = array_merge(
            array(
                'api_key'              => '',
                'cache_time'           => '600',
                'cache_folder_player'  => $_SERVER['DOCUMENT_ROOT'] . '/cache/HypixelAPI/player/',
                'cache_uuid_table'     => 'uuid_table.json',
                'cache_folder_guild'   => $_SERVER['DOCUMENT_ROOT'] . '/cache/HypixelAPI/guild/',
                'cache_byPlayer_table' => 'byPlayer_table.json',
                'cache_byName_table'   => 'byName_table.json'
            ),
            $input
        );

        if(!file_exists($this->options['cache_folder_player'])) {
            mkdir($this->options['cache_folder_player'], 0777, true);
        }

        if(!file_exists($this->options['cache_folder_guild'])) {
            mkdir($this->options['cache_folder_guild'], 0777, true);
        }
    }

    public function set($input)
    {
        foreach($input as $key=>$val)
        {
            $this->options[$key] = $val;
        }
    }

    public function setKey($key)
    {
        $this->options['api_key'] = $key;
    }
    public function getKey()
    {
        return $this->options['api_key'];
    }

    public function fetch($request, $key, $val)
    {
        $response = file_get_contents('https://api.hypixel.net/' . $request . '?key=' . $this->getKey() . '&' . $key . '=' . $val);
        return json_decode($response, true);
    }

    public function getPlayer($keypair = array())
    {
        $pairs = array_merge(
            array(
                'name' => '',
                'uuid' => ''
            ),
            $keypair
        );

        foreach ($pairs as $key => $val) {
            $val = strtolower($val);
            if ($val != '') {
                if ($key == 'uuid') {
                    $filename = $this->options['cache_folder_player'] . $this->options['cache_uuid_table'];
                    if (!file_exists($filename)) {
                        $file = fopen($filename, 'w');
                        fwrite($file, json_encode(array()));
                        fclose($file);
                        $content = array();
                    }
                    else
                    {
                        $file = fopen($filename, 'r');
                        $content = json_decode(fread($file, filesize($filename)), true);
                        fclose($file);
                    }

                    if(array_key_exists($val, $content))
                    {
                        if(time() - $this->options['cache_time'] < $content[$val]['timestamp'])
                        {
                            // get cache
                            return $this->getPlayer(array('name'=>$content[$val]['name']));
                        }
                    }

                    // new/update entry
                    $response = $this->fetch('player', $key, $val);
                    if ($response['success']) {
                        $content[$val] = array('timestamp'=>time(), 'name'=>$response['player']['displayname']);
                        $file = fopen($filename, 'w');
                        fwrite($file, json_encode($content));
                        fclose($file);

                        return new Player($response);
                    }

                }

                if ($key == 'name') {
                    $filename = $this->options['cache_folder_player'] . $key . '/' . $this->getCacheFileName($val) . '.json';
                    if (file_exists($filename)) {
                        if (time() - $this->options['cache_time'] < filemtime($filename)) {
                            // get cache
                            $file = fopen($filename, 'r');
                            $content = fread($file, filesize($filename));
                            fclose($file);

                            return new Player(json_decode($content, true));
                        }
                    }
                    else
                    {
                        mkdir(dirname($filename), 0777, true);
                    }

                    // new/update entry
                    $response = $this->fetch('player', $key, $val);
                    if ($response['success']) {
                        $file = fopen($filename, 'w');
                        fwrite($file, json_encode($response));
                        fclose($file);

                        return new Player($response);
                    }
                }
            }
        }

        return null;
    }

    public function getGuild($keypair = array())
    {
        $pairs = array_merge(
            array(
                'byPlayer' => '',
                'byName'   => '',
                'id'       => ''
            ),
            $keypair
        );

        foreach ($pairs as $key => $val) {
            if ($val != '') {
                if ($key == 'byPlayer' || $key == 'byName') {
                    $filename = $this->options['cache_folder_guild'] . $this->options['cache_' . $key . '_table'];
                    if (!file_exists($filename)) {
                        $file = fopen($filename, 'w');
                        fwrite($file, json_encode(array()));
                        fclose($file);
                        $content = array();
                    } else {
                        $file = fopen($filename, 'r');
                        $content = json_decode(fread($file, filesize($filename)), true);
                        fclose($file);
                    }

                    if (array_key_exists($val, $content)) {
                        if (time() - $this->options['cache_time'] < $content[$val]['timestamp']) {
                            // get cache
                            return $this->getGuild(array('id' => $content[$val]['guild']));
                        }
                    }

                    // new/update entry
                    $response = $this->fetch('findGuild', $key, $val);
                    if ($response['success']) {
                        $content[$val] = array('timestamp' => time(), 'guild' => $response['guild']);
                        $file = fopen($filename, 'w');
                        fwrite($file, json_encode($content));
                        fclose($file);

                        return $this->getGuild(array('id' => $response['guild']));
                    }
                }

                if ($key == 'id') {
                    $filename = $this->options['cache_folder_guild'] . $key . '/' . $val . '.json';
                    if (file_exists($filename)) {
                        if (time() - $this->options['cache_time'] < filemtime($filename)) {
                            // get cache
                            $file = fopen($filename, 'r');
                            $content = fread($file, filesize($filename));
                            fclose($file);

                            return new Guild(json_decode($content, true));
                        }
                    }
                    else
                    {
                        mkdir(dirname($filename), 0777, true);
                    }

                    // new/update entry
                    $response = $this->fetch('guild', $key, $val);
                    if ($response['success']) {
                        $file = fopen($filename, 'w');
                        fwrite($file, json_encode($response));
                        fclose($file);

                        return new Guild($response);
                    }
                }
            }
        }
        return null;
    }

    private function getCacheFileName($input)
    {
        if(strlen($input) < 3 )
        {
            return implode('/', str_split($input, 1));
        }

        return substr($input, 0, 1) . '/' . substr($input, 1, 1) . '/' . substr($input, 2);

    }

}

class HypixelObject
{
    public  $infojson;

    public function getRaw()
    {
        return $this->infojson;
    }

    public function get($key, $implicit = false)
    {
        if(!$implicit)
        {
            $return = $this->infojson;
            foreach(explode(".", $key) as $split)
            {
                $return = $return[$split];
            }
            return $return;
        }
        return $this->infojson[$key];
    }

    public function getId()
    {
        return $this->get('_id', true);
    }
}

class Player extends HypixelObject
{
    public function __construct($json)
    {
        $this->infojson = $json['player'];
    }

    public function getName()
    {
        return $this->get('displayname', true);
    }

    public function getStats()
    {
        return $this->get('stats', true);
    }

    public function isPreEULA()
    {
        return $this->get('eulaCoins', true);
    }

    public function getLevel()
    {
        return $this->get('networkLevel', true) + 1;
    }

    public function getRank()
    {
        return ($this->get('rank', true) == 'NORMAL' || $this->get('rank', true) == null) ? ($this->get('packageRank', true) ? $this->get('packageRank', true) : 'DEFAULT') : $this->get('rank', true);
    }
}

class Guild extends HypixelObject
{
    private $members;

    public function __construct($json)
    {
        $this->infojson = $json['guild'];
    }

    public function getName()
    {
        return $this->get('name', true);
    }

    public function getTag()
    {
        return $this->get('tag', true);
    }

    public function getMemberList()
    {
        if($this->members != null)
            return $this->members;
        $this->members = new MemberList($this->infojson['members']);
        return $this->getMemberList();
    }
}

class MemberList
{
    private $list;

    public function __construct($json)
    {
        $list = array();

        foreach($json as $player)
        {
            $rank = $player['rank'];
            if(!in_array($rank, array_keys($list)))
            {
                $list[$rank] = array();
            }

            array_push($list[$rank], $player);
        }

        $this->list = $list;
    }

    public function getList()
    {
        return $this->list;
    }
}