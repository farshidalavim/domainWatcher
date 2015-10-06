<?php

set_time_limit(3000);

class watcher {
    /* The url to watch */
    var $___url;

    /* The parsed array of the url */
    var $__parseUrl;

    /* The directory of logs */
    var $___logDir;

    /* The directory of contents */
    var $___contentDir;

    /* The filename to store content */
    var $___logFile;

    /* the admin email */
    var $admin;

    static function validateUrl($___url) {
        if(!preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i", $___url))
            return FALSE;

        return TRUE;
    }

    static function validateDomain($___url) {
        $f = @fopen($___url, "r");
        if(!$f)
            return FALSE;

        return TRUE;
    }

    static function parseUrl($___url) {
        return parse_url($___url);
    }

    function __construct($___url, $___logDir, $___contentDir, $admin) {
        if(!watcher::validateUrl($___url))
            throw new Exception("Please provide a valid url to watch");

        $f = @fopen($___url, "r");
        if(!watcher::validateDomain($___url))
            throw new Exception("Unable to browse the domain");

        if(!is_dir($___logDir))
            if(!mkdir($___logDir))
                throw new Exception("The log directory doesn't exist or unable to create the log directory");

        if(!is_dir($___contentDir))
            if(!mkdir($___contentDir))
                throw new Exception("The log directory doesn't exist or unable to create the log directory");

        if(!filter_var($admin, FILTER_VALIDATE_EMAIL))
            throw new Exception("Invalid email address provided");

        $this->__parseUrl = watcher::parseUrl($___url);

        if(!isset($this->__parseUrl['host']))
            throw new Exception("Invalid url host");

        $this->___url = $___url;
        $this->___logDir = $___logDir;
        $this->___contentDir = $___contentDir;
        $this->admin = $admin;

        $this->___logFile = "log-".date("Y-m-d").".txt";
    }

    private function getContentAddress() {
        $__webDir = $this->___contentDir."/".$this->__parseUrl['host'];

        if(!file_exists($__webDir))
            if(!mkdir($__webDir))
                throw new Exception("Unable to create the website content directory");

        return $__webDir."/";
    }

    private function getLogAddress() {
        $__webDir = $this->___logDir."/".$this->__parseUrl['host'];

        if(!file_exists($__webDir))
            if(!mkdir($__webDir))
                throw new Exception("Unable to create the website log directory");

        return $__webDir."/";
    }

    private function oldContent($___address) {
        if(!file_exists($___address)) return FALSE;

        $content = file_get_contents($___address, FILE_USE_INCLUDE_PATH);

        if(empty($content)) return FALSE;

        return $content;
    }

    private function logContent($content, $__address) {
        if(empty($content))
            throw new Exception("Invalid content");

        $___file = fopen($__address, "w+");

        if(!$___file)
            throw new Exception("Unable to create or read the content file");

        fwrite($___file, md5($content));
        fclose($___file);

        return TRUE;
    }

    function log($content, $url) {
        if(empty($content))
            throw new Exception("Invalid content");

        $___file = fopen(self::getLogAddress()."log.txt", "a+");

        if(!$___file)
            throw new Exception("Unable to create or read the content file");

        fwrite($___file, "Content for ".$url." updated on ".date("Y-m-d H:i:s"). ", the new content is ".md5($content)." reported to ".$this->admin."\r\n");
        fclose($___file);

        return TRUE;
    }

    function execute() {
        try {
            /* if homepage content has changed - notify the admin */
            $homepage = "http://".$this->__parseUrl['host']."/";
            $___handler = fopen($homepage, "rb");
            $content = stream_get_contents($___handler);
            fclose($___handler);
            $__contentAddress = self::getContentAddress()."homepage.txt";
            $oldContent = self::oldContent($__contentAddress);
            if(md5($content) !== $oldContent) {
                self::logContent($content, $__contentAddress);
                @mail($this->admin, "A recent content changes on ".$this->___url, "Hi admin, the ".$this->___url." content has changed");
                self::log($content, $homepage);
            }

            /* finding subpages */
            $html = file_get_contents($this->___url);
            $doc = new DOMDocument();
            @$doc->loadHTML($html);
            $xpath = new DOMXpath($doc);
            $nodes = $xpath->query('//a');

            $basename = strtolower($this->__parseUrl['host']);

            foreach($nodes as $node) {
                $___url = $node->getAttribute('href');

                /* if not a valid url - then add the homepage basename then check in case the subpage doesn't include the parent url */
                if(!watcher::validateUrl($___url))
                    $___url = "http://".$this->__parseUrl['host']."/".$___url;

                /* if it is a link to home page then continue */
                if(in_array(strtolower($___url), array( $basename."/", "http://".$basename, "http://".$basename."/",
                                                        "http://www.".$basename."/", "https://".$basename."/", "https://www.".$basename."/")))
                    continue;

                /* if it is not a valid domain then continue */
                if(!watcher::validateDomain($___url))
                    continue;

                /* if it is an external link then continue */
                if(strpos($___url, $this->__parseUrl['host']) === FALSE)
                    continue;

                $__parseUrl = watcher::parseUrl($___url);

                echo "<pre>"; print_r(array($___url, basename($___url), basename($this->___url), $__parseUrl)); echo "</pre>";

                $___handler = fopen($___url, "rb");
                $content = stream_get_contents($___handler);
                fclose($___handler);

                $__contentAddress = self::getContentAddress().basename($___url).".txt";
                $oldContent = self::oldContent($__contentAddress);

                if(md5($content) !== $oldContent) {
                    self::logContent($content, $__contentAddress);
                    @mail($this->admin, "A recent content changes on ".$___url, "Hi admin, the content of ".$___url." from ".$this->___url." has changed");
                    self::log($content, $___url);
                }

            }

        } catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
?>