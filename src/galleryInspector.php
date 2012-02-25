<?php
    class GalleryInspector {
    
        const JSON = 'json';
        const XML = 'xml';
        const DEF_ITEMS_TABLE = 'items';
        
        private $galleryDBHost;
        private $galleryDBUser;
        private $galleryDBPass;
        private $galleryDB;
        private $conn;
        private $format;
        private $itemsTable;
        private $galleryURL;
        
        public function __construct ($galleryDBHost, $galleryDBUser, $galleryDBPass, $galleryDB) {
            $this->galleryDBHost = $galleryDBHost;
            $this->galleryDBUser = $galleryDBUser;
            $this->galleryDBPass = $galleryDBPass;
            $this->galleryDB = $galleryDB;
            
            $this->conn = new mysqli ($this->galleryDBHost, $this->galleryDBUser, $this->galleryDBPass, $this->galleryDB);
            
            //Not using OO here since $mysqli->connect_error was broken until PHP 5.2.9 and 5.3.0
            //and my target was 5.2.1
            if (mysqli_connect_error()) {
                throw new Exception ('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
            }
            
            $this->format = self::JSON;
            $this->itemsTable = self::DEF_ITEMS_TABLE;
            $this->galleryPath = "";
        }
        
        public function getConnectionObject() {
            return $this->conn;
        }
        
        public function setFormat ($format = self::JSON) {
            $this->format = $format;
        }
        
        public function getFormat() {
            return $this->format;
        }
        
        public function setItemsTable ($itemsTable = self::DEF_ITEMS_TABLE) {
            $this->itemsTable = $itemsTable;
        }
        
        public function getItemsTable() {
            return $this->itemsTable;
        }
        
        public function setGalleryURL ($URL) {
            if (GalleryInspector::isURL($URL)) {
                $this->galleryURL = $URL;
            }
            else {
                throw new Exception ('Parameter is not a valid URL');
            }
        }
        
        private function getAlbums ($upToLevel = -1, $withPhotos = true) {
            $q = "SELECT id, album_cover_item_id, level, parent_id, title, relative_path_cache, thumb_height, thumb_width FROM " . $this->itemsTable .
                 " WHERE type = 'album'" .
                 ($upToLevel == -1 ? "" : " AND level <= " . $upToLevel) .
                 " ORDER BY 'level' ASC";
            
            $ps;     
            if ($withPhotos) {
                $ps = $this->getAllPhotos();
            }
                 
            if ($result = $this->conn->query ($q)) {                
                $results = array();
                //Get all the albums and put them in an associative array
                //where the key is the item id
                while ($row = $result->fetch_assoc()) {
                    //Let's also add the photos that are in each album (if any)
                    if (isset($ps)) {
                        $photos = $ps [$row['id']];
                        if (isset($photos) && count($photos) > 0) {
                            $row ['photos'] = $photos;
                        }
                    }
                    if (strlen($row['relative_path_cache']) > 0) {
                        $row['thumb_path'] = (strlen($this->galleryURL) > 0 ? $this->galleryURL . '/' : '') .'var/thumbs/' . $row['relative_path_cache'] . '/.album.jpg';
                    }
                    $results [$row['id']] = $row;
                }
                $result->close();
                
                //Now iterate through the items (by reference)
                //and assign to each item's children key an array of descendants
                //of that item
                foreach ($results as &$r) {
                    $parent = $r['parent_id'];
                    if ($parent == 0) {
                        continue;
                    }
                    if (!array_key_exists ('children', $results [$parent])) {
                        $results [$parent] ['children'] = array();
                    }
                    $results [$parent] ['children'][] = &$r;
                    
                }
                //Return the first item in the array, which will include also
                //the children, the children's childrens, etc.
                return reset($results);
            }
            else {
                throw new Exception ('Query error');
            }
        }
        
        private function getPhotosInAlbum ($album) {
            $q = "SELECT id, relative_path_cache, title, thumb_height, thumb_width FROM " . $this->itemsTable .
                 " WHERE parent_id = " . $album . " AND type = 'photo'";
            if ($result = $this->conn->query ($q)) {                
                $results = array();
                //Get all the albums and put them in an associative array
                //where the key is the item id
                while ($row = $result->fetch_assoc()) {
                    $row['path'] = (strlen($this->galleryURL) > 0 ? $this->galleryURL . '/' : '') .'var/albums/' . $row['relative_path_cache'];
                    $row['thumb_path'] = (strlen($this->galleryURL) > 0 ? $this->galleryURL . '/' : '') .'var/thumbs/' . $row['relative_path_cache'];
                    $results[] = $row;
                }
                $result->close();
                return $results;
            }
            else {
                throw new Exception ('Query error');
            }
        }
        
        private function getAllPhotos() {
            $q = "SELECT id, relative_path_cache, title, parent_id, thumb_height, thumb_width FROM " . $this->itemsTable .
                 " WHERE type = 'photo'";
            if ($result = $this->conn->query ($q)) {
                $results = array();
                //Get all the albums and put them in an associative array
                //where the key is the item id
                while ($row = $result->fetch_assoc()) {
                    $row['path'] = (strlen($this->galleryURL) > 0 ? $this->galleryURL . '/' : '') .'var/albums/' . $row['relative_path_cache'];
                    $row['thumb_path'] = (strlen($this->galleryURL) > 0 ? $this->galleryURL . '/' : '') .'var/thumbs/' . $row['relative_path_cache'];
                    if (!is_array($results[$row['parent_id']])) {
                        $results[$row['parent_id']] = array();
                    }
                    array_push ($results[$row['parent_id']], $row);
                }
                $result->close();
                return $results;
            }
            else {
                throw new Exception ('Query error');
            }
        }
        
        private function getLastPhotoInsertionDate() {
            $q = "SELECT updated FROM " . $this->itemsTable . " ORDER BY updated DESC LIMIT 1";
            if ($result = $this->conn->query ($q)) {
                $row = $result->fetch_assoc();
                $date = date("d/m/Y", $row['updated']);
                $result->close();
                return $date;
            }
            else {
                throw new Exception ('Query error');
            }
        }
        
        private static function isURL ($url) {
            $pattern = '/^(([\w]+:)?\/\/)?(([\d\w]|%[a-fA-f\d]{2,2})+(:([\d\w]|%[a-fA-f\d]{2,2})+)?@)?([\d\w][-\d\w]{0,253}[\d\w]\.)+[\w]{2,4}(:[\d]+)?(\/([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)*(\?(&amp;?([-+_~.\d\w]|%[a-fA-f\d]{2,2})=?)*)?(#([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)?$/';
            return preg_match($pattern, $url);
        }
        
        public function replyWithAlbums() {
            $t0 = microtime(true);
            $albums = $this->getAlbums();
            $response = array();
            $response ['albums'] = $albums;
            //$response ['phpVersion'] = phpversion();
            $response ['executionTime'] = microtime(true) - $t0;
            $response ['lastUpdated'] = $this->getLastPhotoInsertionDate();
            switch ($this->format) {
                case self::JSON:
                    header ('Cache-Control: no-cache, must-revalidate');
                    header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                    header ('Content-type: application/json');
                    echo str_replace('\\/', '/', json_encode ($response));
                    break;
                case self::XML:
                    throw new Exception ('XML format not supported yet');
                    break;
            }
        }
        
        public function replyWithPhotosForAlbum ($album) {
            $t0 = microtime(true);
            $photos = $this->getPhotosInAlbum ($album);
            $response = array();
            $response ['photos'] = $albums;
            //$response ['phpVersion'] = phpversion();
            $response ['executionTime'] = microtime(true) - $t0;
            switch ($this->format) {
                case self::JSON:
                    header ('Cache-Control: no-cache, must-revalidate');
                    header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                    header ('Content-type: application/json');
                    echo str_replace('\\/', '/', json_encode ($photos));
                    break;
                case self::XML:
                    throw new Exception ('XML format not supported yet');
                    break;
            }
        }
        
        public function __destruct() {
            $this->conn->close();
        }
    }
    
    /*
    function galleryInspectorTest() {
        try {
            $gi = new GalleryInspector ('localhost', 'albertob_gallery', 'my@gallery', 'albertob_gallery');
            $gi->setItemsTable ('g3_items');
            $gi->setGalleryURL('http://albertobarbaresco.com/incostruzione/gallery');
            $gi->replyWithAlbums();
        }
        catch (Exception $e) {
            die ($e);
        }
    }
    //echo '<pre>';
    galleryInspectorTest();
    //echo '</pre>';
    */
?>