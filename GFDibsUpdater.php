<?php

class GFDibsUpdater{
	protected $Author;
	protected $AuthorURI;
	protected $Description;
	protected $InstalledVersion;
	protected $NewVersion; // from github
	protected $Package; // download url
	protected $PluginFile;
	protected $PluginName;
	public $Slug;
	protected $Plugin;
	protected $IsActive;

	function __construct($plugin_file ){
		$this->PluginFile = $plugin_file;
		add_filter( "plugins_api", array( $this, "setPluginInfo" ), 10, 3 );
		add_filter( "pre_set_site_transient_update_plugins", array( $this, "setTransitent" ), 1 );
		add_action( 'admin_notices', array($this, 'getLicenceKeyInfo') );
		add_action( 'activated_plugin', array($this, 'registerPlugin') , 10, 2 );
		add_filter( "upgrader_post_install", array( $this, "afterUpdate" ), 10, 3 );
	}


	function afterUpdate( $true, $hook_extra, $result ){
		_debug('after update');
		global $wp_filesystem;
		//$this->getCurrentVersionInfo();

		$filename = basename($this->PluginFile);
		$slug = substr($filename, 0,strrpos($filename,'.'));
		$pluginFolder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $slug ;

		$moved = $wp_filesystem->move( $result['destination'], $pluginFolder );

		if ( $moved ){
			$result['destination'] = $pluginFolder;

			if ( function_exists('activate_plugin') ){
				activate_plugin( $slug."/".$filename);
			}
		}

		return $result;
	}


	public static function getHost($dir){
		_debug('GFDibsUpdater::getHost');

		if ( is_numeric(strpos($_SERVER['HTTP_HOST'], 'sites')) ){
			$host = 'http://'.$_SERVER['HTTP_HOST']."/nettbutikk/";
		}
		else{
			$host = 'https://www.mediebruket.no/nettbutikk/';
		}
		$host = $host.$dir;

		_debug($host);

		return $host;
	}

	public static function buildUrl($dir, $query_vars){
		$host = self::getHost($dir);
		$url = $host."?".http_build_query($query_vars);

		return $url;
	}


	function getCurrentVersionInfo(){
		$plugin_data = get_plugin_data( $this->PluginFile, $markup = false, $translate = false );

		$this->Author 					= $plugin_data['Author'];
		$this->AuthorURI 				= $plugin_data['AuthorURI'];
		$this->Description 			= $plugin_data['Description'];
		$this->InstalledVersion = $plugin_data['Version'];
   	$this->Slug 						= substr(basename($this->PluginFile), 0, strpos(basename($this->PluginFile), '.')) ;
   	$this->PluginName 			= $plugin_data['Name'];
   	$this->Plugin 					= $this->Slug."/".$this->Slug.'.php';
	}


	// wp-admin/plugins.php plugins (overview)
	function setTransitent( $transient ){
		$current_tag = $this->updateAvailable();

		if ( strlen($current_tag) && $current_tag != '1' ){
			$tag = json_decode($current_tag);
			$this->getCurrentVersionInfo();

			$obj = new stdClass();
   		$obj->slug 				= $this->Slug;
   		$obj->plugin 			= $this->Plugin;
			$obj->new_version = $tag->name;
			$obj->url 				= $this->AuthorURI;


			$query_vars = array(
				'plugin' 	=> $this->Slug,
				'host' 		=> $_SERVER['HTTP_HOST'],
				'licence' => get_option( $this->Slug.'_licence_key' ),
				'version' => $this->InstalledVersion
			);

			$url = self::buildUrl('getupdate', $query_vars);
			// _debug($url);
			$response = wp_remote_retrieve_body(wp_remote_get($url) );

			if ( $response && !is_numeric($response) ){
				$obj->package = $response;
				$transient->response[$this->Plugin] = $obj;
			}
		}

		// _debug($transient);

		return $transient;
	}


	// wp-admin/plugin-install.php (changelog)
  function setPluginInfo( $false, $action, $response ){
  	$this->getCurrentVersionInfo();

   	if ( $response->slug != $this->Slug){
   		return false;
   	}
   	else{
   		$Release = json_decode( $this->getReleaseInfo() );

 		  $response->last_updated 	= $Release->published_at;
			$response->slug 					= $this->Slug;
			$response->plugin_name  	= $this->Plugin;
			$response->version 				= $Release->tag_name;
			$response->author 				= $this->Author;
			$response->homepage 			= $this->AuthorURI;
			$response->name 					= $this->PluginName;

			$changelog = (string)$Release->body;
			if ( class_exists('Parsedown') && is_string($changelog) ){
				$Parsedown = new Parsedown();
				$changelog = $Parsedown->text($changelog);
			}

			$response->sections =
				array(
        	'Description'   => $this->Description,
          'changelog'     => $changelog
				);

			return $response;
   	}
	}


	function updateAvailable(){
		$this->getCurrentVersionInfo();

		$query_vars = array(
				'plugin' 	=> $this->Slug,
				'host' 		=> $_SERVER['HTTP_HOST'],
				'licence' => get_option( $this->Slug.'_licence_key' ),
				'version' => $this->InstalledVersion
			);

		$url = self::buildUrl('update', $query_vars);

		_debug($url);
		$response = wp_remote_retrieve_body(wp_remote_get( $url ));

		return $response;
	}


	function getReleaseInfo(){
		$query_vars = array( 'plugin' => $this->Slug );
		$url = self::buildUrl('releases', $query_vars);

		_debug($url);
		$response = wp_remote_retrieve_body(wp_remote_get( $url ));

		return $response;
	}


	function getLicenceKeyInfo(){
		$this->getCurrentVersionInfo();

		$valid = get_transient($this->Slug.'_last_check' );

		if ( !$valid ){
			$valid = $this->checkLicenceKey();
			set_transient( $this->Slug.'_last_check', $valid, 0 );
		}

		if ( !isset($_GET['subview'])|| $_GET['subview']!='DIBS'){
			if ( $valid == '3' ){
				$class = "error";
				$message = sprintf("%s: <a href='%s'>Licence key not valid</a>", $this->PluginName, admin_url('admin.php?page=gf_settings&subview=DIBS') );
				echo "<div class=\"$class\"> <p>$message</p></div>";
			}
		}
	}


	function checkLicenceKey(){
		$this->getCurrentVersionInfo();

		$query_vars = array(
			'plugin' 	=> $this->Slug,
			'host' 		=> $_SERVER['HTTP_HOST'],
			'licence' => get_option( $this->Slug.'_licence_key' )
			);

		$url = self::buildUrl('licence', $query_vars);

		_debug($url);
		$response = wp_remote_retrieve_body(wp_remote_get( $url ));

		return $response;
	}


	function registerPlugin($plugin = null, $network_activation = null ){
		$query_vars = array(
			'plugin' 	=> 'Gravity Forms DIBS Add-on',
			'host' 		=> $_SERVER['HTTP_HOST'],
			);

		$url = self::buildUrl('activation', $query_vars);
		_debug($url);
		wp_remote_get( $url );
	}


}
?>