<?php
/**
 * Pickles 2 contents editor
 */
namespace pickles2\libs\contentsEditor;

/**
 * Pickles 2 contents editor core class
 *
 * @author Tomoya Koyanagi <tomk79@gmail.com>
 */
class main{

	/** Filesystem Utility */
	private $fs;

	/**
	 * 編集対象のモード
	 * コンテンツ以外にも対応範囲を拡大
	 * - `page_content` = ページコンテンツ(デフォルト)
	 * - `theme_layout` = テーマレイアウトテンプレート(px2-multithemeの仕様に準拠)
	 */
	private $target_mode;

	/**
	 * ページのパス
	 * `target_mode` が `theme_layout` の場合、
	 * `page_path` は `{$theme_id}/{$layout_id}.html` の形式を取る
	 */
	private $page_path;

	/** Entry Script path */
	private $entryScript;

	/**
	 * テーマID
	 * `target_mode` が `theme_layout` の場合に値を持つ。
	 * `this.page_path` をパースして生成。
	 */
	private $theme_id;
	/**
	 * レイアウトID
	 * `target_mode` が `theme_layout` の場合に値を持つ。
	 * `this.page_path` をパースして生成。
	 */
	private $layout_id;

	/** PHPコマンド設定 */
	private $php_command;

	/** Pickles 2 プロジェクト環境情報 */
	private $pjInfo;
	private $px2conf,
		$pageInfo,
		$documentRoot,
		$contRoot,
		$realpathDataDir,
		$pathResourceDir,
		$realpathFiles;

	/** オプション */
	private $options;

	/**
	 * Constructor
	 */
	public function __construct(){
	}

	/**
	 * Initialize
	 */
	public function init( $options ){
		// var_dump(options);
		if(!is_array($options)){
			$options = array();
		}
		if( !@strlen( $options['appMode'] ) ){
			$options['appMode'] = 'web'; // web | desktop
		}
		if( !@is_array( $options['customFields'] ) ){
			$options['customFields'] = array(); // custom fields
		}
		if( !@is_array( $options['customFieldsIncludePath'] ) ){
			$options['customFieldsIncludePath'] = array(); // custom fields include path (for cliend libs)
		}
		if( !@is_callable( $options['log'] ) ){
			$options['log'] = function($msg){
				// var_dump($msg);
			};
		}
		$this->php_command = array(
			'php'=>'php',
			'php_ini'=>null,
			'php_extension_dir'=>null,
		);
		if( strlen(@$options['php']) ){
			$this->php_command['php'] = $options['php'];
		}
		if( strlen(@$options['php_ini']) ){
			$this->php_command['php_ini'] = $options['php_ini'];
		}
		if( strlen(@$options['php_extension_dir']) ){
			$this->php_command['php_extension_dir'] = $options['php_extension_dir'];
		}

		$this->fs = new \tomk79\filesystem();
		$this->entryScript = $options['entryScript'];
		$this->target_mode = (@strlen($options['target_mode']) ? $options['target_mode'] : 'page_content');
		$this->page_path = @$options['page_path'];
		if(!is_string($this->page_path)){
			// 編集対象ページが指定されていない場合
			return;
		}

		$this->options = $options;

		$this->page_path = preg_replace( '/^(alias[0-9]*\:)?\/+/s', '/', $this->page_path );
		$this->page_path = preg_replace( '/\{(?:\*|\$)[\s\S]*\}/s', '', $this->page_path );

		$pjInfo = $this->getProjectInfo();
		// var_dump($pjInfo);

		$this->pjInfo = $pjInfo;
		$this->px2conf = $pjInfo['conf'];
		$this->pageInfo = $pjInfo['pageInfo'];
		$this->documentRoot = $pjInfo['documentRoot'];
		$this->contRoot = $pjInfo['contRoot'];
		$this->realpathDataDir = $pjInfo['realpathDataDir'];
		$this->pathResourceDir = $pjInfo['pathResourceDir'];
		$this->realpathFiles = $pjInfo['realpathFiles'];

		if( $this->target_mode == 'theme_layout' ){
			if( preg_match('/^\/([\s\S]+?)\/([\s\S]+)\.html$/', $this->page_path, $matched) ){
				$this->theme_id = $matched[1];
				$this->layout_id = $matched[2];
			}
			$this->documentRoot = $pjInfo['realpathThemeCollectionDir'];
			$this->contRoot = '/';
			$this->realpathFiles = $pjInfo['realpathThemeCollectionDir'].$this->theme_id.'/theme_files/layouts/'.$this->layout_id.'/';
			$this->pathResourceDir = '/'.$this->theme_id.'/theme_files/layouts/'.$this->layout_id.'/resources/';
			$this->realpathDataDir = $pjInfo['realpathThemeCollectionDir'].$this->theme_id.'/guieditor.ignore/'.$this->layout_id.'/data/';
		}

		return;
	}

	/**
	 * $fs
	 */
	public function fs(){
		return $this->fs;
	}

	/**
	 * $target_mode を取得
	 */
	public function get_target_mode(){
		return $this->target_mode;
	}

	/**
	 * $theme_id を取得
	 */
	public function get_theme_id(){
		return $this->theme_id;
	}

	/**
	 * $layout_id を取得
	 */
	public function get_layout_id(){
		return $this->layout_id;
	}

	/**
	 * プロジェクトの設定情報を取得する
	 */
	public function get_project_conf(){
		$conf = $this->px2query(
			'/?PX=api.get.config',
			array(
				"output" => "json"
			)
		);
		return $conf;
		return;
	}

	/**
	 * アプリケーションの実行モード設定を取得する
	 * @return string 'web'|'desktop'
	 */
	public function get_app_mode(){
		$rtn = $this->options['appMode'];
		switch($rtn){
			case 'web':
			case 'desktop':
				break;
			default:
				$rtn = 'web';
				break;
		}
		return $rtn;
	}

	/**
	 * $realpathFiles
	 */
	public function get_realpath_files(){
		return $this->realpathFiles;
	}

	/**
	 * $documentRoot
	 */
	public function get_document_root(){
		return $this->documentRoot;
	}

	/**
	 * $page_path
	 */
	public function get_page_path(){
		return $this->page_path;
	}

	/**
	 * $contRoot
	 */
	public function get_cont_root(){
		return $this->contRoot;
	}

	/**
	 * $options
	 */
	public function options(){
		return $this->options;
	}

	/**
	 * ブラウザでURLを開く
	 */
	public function openUrlInBrowser( $url ){
		if( $this->get_app_mode() != 'desktop' ){
			return false;
		}
		if( realpath('/') == '/' ){
			// Linux or macOS
			exec('open '.json_encode($url));
		}else{
			// Windows
			exec('explorer '.json_encode($url));
		}
		return true;
	}

	/**
	 * リソースフォルダを開く
	 */
	public function openResourceDir( $path ){
		if( $this->get_app_mode() != 'desktop' ){
			return false;
		}
		if( !is_dir($this->realpathFiles) ){
			$this->fs()->mkdir($this->realpathFiles);
		}
		$realpath_target = $this->fs()->get_realpath($this->realpathFiles.'/'.$path);
		if( !is_dir(dirname($realpath_target)) ){
			$this->fs()->mkdir(dirname($realpath_target));
		}

		if( realpath('/') == '/' ){
			// Linux or macOS
			exec('open '.json_encode($realpath_target));
		}else{
			// Windows
			exec('explorer '.json_encode($realpath_target));
		}
		return true;
	}

	/**
	 * プロジェクト情報をまとめて取得する
	 */
	private function getProjectInfo(){
		$pjInfo = array();

		$allData = $this->px2query(
			$this->page_path.'?PX=px2dthelper.get.all',
			array(
				"output" => "json"
			)
		);

		$pjInfo['conf'] = $allData->config;
		$pjInfo['pageInfo'] = $allData->page_info;
		$pjInfo['contRoot'] = $allData->path_controot;
		$pjInfo['documentRoot'] = $allData->realpath_docroot;
		$pjInfo['realpathFiles'] = $allData->realpath_files;
		$pjInfo['pathFiles'] = $allData->path_files;
		$pjInfo['realpathThemeCollectionDir'] = $allData->realpath_theme_collection_dir;
		$pjInfo['realpathDataDir'] = $allData->realpath_data_dir;
		$pjInfo['pathResourceDir'] = $allData->path_resource_dir;
		$pjInfo['realpath_homedir'] = $allData->realpath_homedir;

		// var_dump($pjInfo);
		return $pjInfo;
	} // getProjectInfo()

	/**
	 * モジュールCSS,JSソースを取得する
	 */
	public function getModuleCssJsSrc($theme_id){
		if(!strlen($theme_id)){
			$theme_id = '';
		}
		$rtn = array(
			'css' => '',
			'js' => ''
		);
		$data = $this->px2query(
			'/?PX=px2dthelper.document_modules.build_css&theme_id='.urlencode($theme_id),
			array(
				"output" => "json"
			)
		);

		// var_dump($data);
		$rtn['css'] .= $data;

		$data = $this->px2query(
			'/?PX=px2dthelper.document_modules.build_js&theme_id='.urlencode($theme_id),
			array(
				"output" => "json"
			)
		);

		// var_dump($data);
		$rtn['js'] .= $data;

		return $rtn;
	} // getModuleCssJsSrc

	/**
	 * コンテンツファイルを初期化する
	 */
	public function init_content_files($editorMode){
		$data = $this->px2query(
			$this->page_path.'?PX=px2dthelper.init_content&editor_mode='.urlencode($editorMode),
			array(
				"output" => "json"
			)
		);
		return $data;
	}

	/**
	 * ページの編集方法を取得する
	 */
	public function check_editor_mode(){
		if( $this->target_mode == 'theme_layout' ){
			// ドキュメントルートの設定上書きがある場合
			// テーマレイアウトの編集等に利用するモード
			// var_dump([$this->documentRoot,
			// 	$this->contRoot,
			// 	$this->realpathFiles,
			// 	$this->pathResourceDir,
			// 	$this->realpathDataDir]);

			if( !is_file( $this->documentRoot . $this->page_path ) ){
				return '.not_exists';
			}
			if( is_file( $this->realpathDataDir . 'data.json' ) ){
				return 'html.gui';
			}
			return 'html';
		}
		$data = $this->px2query(
			$this->page_path.'?PX=px2dthelper.check_editor_mode',
			array(
				"output" => "json"
			)
		);
		// var_dump($data);
		return $data;
	}

	/**
	 * create initialize options for broccoli-html-editor
	 */
	public function createBroccoliInitOptions(){
		$broccoliInitializeOptions = array();
		$px2ce = $this;

		$page_path = $this->page_path;
		$px2conf = $this->px2conf;
		$pageInfo = $this->pageInfo;
		$contRoot = $this->contRoot;
		$documentRoot = $this->documentRoot;
		$realpathDataDir = $this->realpathDataDir;
		$pathResourceDir = $this->pathResourceDir;
		$pathsModuleTemplate = array();
		$bindTemplate = function(){};

		$customFields = array();
		$page_content = $this->page_path;
		if( strlen(@$pageInfo->content) ){
			$page_content = $pageInfo->content;
		}

		// フィールドを拡張

		// px2ce が拡張するフィールド
		// $customFields['table'] = '........'; // TODO: 未実装

		// 呼び出し元アプリが拡張するフィールド
		foreach( $this->options['customFields'] as $idx=>$customField ){
			$customFields[$idx] = $this->options['customFields'][$idx];
		}

		// プロジェクトが拡張するフィールド
		$confCustomFields = @$px2conf->plugins->px2dt->guieditor->custom_fields;
		foreach( $confCustomFields as $fieldName=>$field ){
			if( $confCustomFields->{$fieldName}->backend->require ){
				// TODO: カスタムフィールドの読み込み、この処理であってる？
				// $path_backend_field = $this->fs()->get_realpath(dirname($this->entryScript).'/'.$confCustomFields->{$fieldName}->backend->require);
				// $customFields[$fieldName] = require_once( $path_backend_field );
			}
		}

		// var_dump($customFields);

		// モジュールテンプレートを収集
		// (指定モジュールをロード)
		if( $this->target_mode == 'theme_layout' ){
			// テーマ編集ではスキップ
		}else{
			foreach( @$px2conf->plugins->px2dt->paths_module_template as $idx=>$path_module_template ){
				$pathsModuleTemplate[$idx] = $this->fs()->get_realpath( $path_module_template.'/', dirname($this->entryScript) );
			}
		}

		// モジュールテンプレートを収集
		// (モジュールフォルダからロード)
		$pathModuleDir = @$px2conf->plugins->px2dt->path_module_templates_dir;
		if( $this->target_mode == 'theme_layout' ){
			// テーマ編集では `broccoli_module_packages` をロードする。
			$pathModuleDir = $this->documentRoot.$this->theme_id.'/broccoli_module_packages/';
		}
		if( !is_string($pathModuleDir) ){
			// モジュールフォルダの指定がない場合
		}else{
			$pathModuleDir = $this->fs()->get_realpath( $pathModuleDir.'/', dirname($this->entryScript) );
			if( !is_dir($pathModuleDir) ){
				// 指定されたモジュールフォルダが存在しない場合
			}else{
				// info.json を読み込み
				$infoJson = array();
				if( is_file($pathModuleDir.'/info.json') ){
					$srcInfoJson = file_get_contents($pathModuleDir.'/info.json');
					$infoJson = json_decode($srcInfoJson);
				}
				if( is_array(@$infoJson->sort) ){
					// 並び順の指定がある場合
					foreach( $infoJson->sort as $idx=>$row ){
						if( @$pathsModuleTemplate[$infoJson->sort[$idx]] ){
							// 既に登録済みのパッケージIDは上書きしない
							// (= paths_module_template の設定を優先)
							continue;
						}
						if( is_dir($pathModuleDir.$infoJson->sort[$idx]) ){
							$pathsModuleTemplate[$infoJson->sort[$idx]] = $pathModuleDir.$infoJson->sort[$idx];
						}
					}
				}

				// モジュールディレクトリ中のパッケージをスキャンして一覧に追加
				$fileList = $this->fs()->ls($pathModuleDir);
				sort($fileList); // sort
				foreach( $fileList as $idx=>$row){
					if( @$pathsModuleTemplate[$fileList[$idx]] ){
						// 既に登録済みのパッケージIDは上書きしない
						// (= paths_module_template の設定を優先)
						continue;
					}
					if( is_dir($pathModuleDir.$fileList[$idx]) ){
						$pathsModuleTemplate[$fileList[$idx]] = $pathModuleDir.$fileList[$idx];
					}
				}
			}
		}

		if( $this->target_mode == 'theme_layout' ){
			$bindTemplate = function($htmls){
				$fin = '';
				foreach( $htmls as $bowlId=>$html ){
					if( $bowlId == 'main' ){
						$fin .= $htmls['main'];
					}else{
						$fin .= "\n";
						$fin .= "\n";
						$fin .= '<'.'?php ob_start(); ?'.'>'."\n";
						$fin .= (strlen($htmls[$bowlId]) ? $htmls[$bowlId]."\n" : '');
						$fin .= '<'.'?php $px->bowl()->send( ob_get_clean(), '.json_encode($bowlId).' ); ?'.'>'."\n";
						$fin .= "\n";
					}
				}
				$template = '<'.'%- body %'.'>';
				$pathThemeLayout = $this->documentRoot.$this->theme_id.'/broccoli_module_packages/_layout.html';
				if(is_file($pathThemeLayout)){
					$template = file_get_contents( $pathThemeLayout );
				}else{
					$template = file_get_contents( __DIR__.'/tpls/broccoli_theme_layout.html' );
				}
				// PHP では ejs は使えないので、単純置換することにした。
				// $fin = $ejs.render($template, {'body': $fin}, {'delimiter': '%'});
				$fin = str_replace('<'.'%- body %'.'>', $fin, $template);

				$baseDir = $this->documentRoot.$this->theme_id.'/theme_files/';
				$this->fs()->mkdir_r( $baseDir );
				$CssJs = $this->getModuleCssJsSrc($this->theme_id);

				$this->fs()->save_file($baseDir.'modules.css', $CssJs['css']);
				$this->fs()->save_file($baseDir.'modules.js', $CssJs['js']);
				return $fin;
			};
		}else{
			$bindTemplate = function($htmls){
				$fin = '';
				foreach( $htmls as $bowlId=>$html ){
					if( $bowlId == 'main' ){
						$fin .= $htmls['main'];
					}else{
						$fin .= "\n";
						$fin .= "\n";
						$fin .= '<'.'?php ob_start(); ?'.'>'."\n";
						$fin .= (strlen($htmls[$bowlId]) ? $htmls[$bowlId]."\n" : '');
						$fin .= '<'.'?php $px->bowl()->send( ob_get_clean(), '.json_encode($bowlId).' ); ?'.'>'."\n";
						$fin .= "\n";
					}
				}
				return $fin;
			};
		}

		$broccoliInitializeOptions = array(
			'appMode' => $this->get_app_mode() ,
			'paths_module_template' => $pathsModuleTemplate ,
			'documentRoot' => $documentRoot,// realpath
			'pathHtml' => $this->fs()->get_realpath($this->contRoot.'/'.$page_content),
			'pathResourceDir' => $this->pathResourceDir,
			'realpathDataDir' =>  $this->realpathDataDir,
			'contents_bowl_name_by' => @$px2conf->plugins->px2dt->contents_bowl_name_by,
			'customFields' => $customFields ,
			'bindTemplate' => $bindTemplate,
			'log' => function($msg){
				// エラー発生時にコールされます。
				// px2ce.log(msg);
			}
		);

		return $broccoliInitializeOptions;
	}

	/**
	 * create broccoli-html-editor object
	 */
	public function createBroccoli(){
		$broccoliInitializeOptions = $this->createBroccoliInitOptions();
		$broccoli = new \broccoliHtmlEditor\broccoliHtmlEditor();
		$broccoli->init($broccoliInitializeOptions);
		return $broccoli;
	}

	/**
	 * 汎用API
	 */
	public function gpi($data){
		$gpi = new gpi($this);
		return $gpi->gpi($data);
	}

	/**
	 * ログファイルにメッセージを出力する
	 */
	public function log($msg){
		$this->options['log']($msg);
		return;
	}

	/**
	 * Pickles 2 にリクエストを発行し、結果を受け取る
	 *
	 * @param string $request_path リクエストを発行する対象のパス
	 * @param array $options Pickles 2 へのコマンド発行時のオプション
	 * - output = 期待する出力形式。`json` を指定すると、リクエストに `-o json` オプションが加えられ、JSON形式で解析済みのオブジェクトが返されます。
	 * - user_agent = `HTTP_USER_AGENT` 文字列。 `user_agent` が空白の場合、または文字列 `PicklesCrawler` を含む場合には、パブリッシュツールからのアクセスであるとみなされます。
	 * @param int &$return_var コマンドの終了コードで上書きされます
	 * @return mixed リクエストの実行結果。
	 * 通常は 得られた標準出力をそのまま文字列として返します。
	 * `output` オプションに `json` が指定された場合、 `json_decode()` された値が返却されます。
	 *
	 * リクエストから標準エラー出力を検出した場合、 `$px->error( $stderr )` に転送します。
	 */
	public function px2query($request_path, $options = null, &$return_var = null){
		if(!is_string($request_path)){
			// $this->error('Invalid argument supplied for 1st option $request_path in $px->internal_sub_request(). It required String value.');
			return false;
		}
		if(!strlen($request_path)){ $request_path = '/'; }
		if(is_null($options)){ $options = array(); }
		$php_command = array();
		array_push( $php_command, addslashes($this->php_command['php']) );
			// ↑ Windows でこれを `escapeshellarg()` でエスケープすると、なぜかエラーに。

		if( strlen(@$this->php_command['php_ini']) ){
			$php_command = array_merge(
				$php_command,
				array(
					'-c', escapeshellarg(@$this->php_command['php_ini']),// ← php.ini のパス
				)
			);
		}
		if( strlen(@$this->php_command['php_extension_dir']) ){
			$php_command = array_merge(
				$php_command,
				array(
					'-d', escapeshellarg(@$this->php_command['php_extension_dir']),// ← php.ini definition
				)
			);
		}
		array_push($php_command, escapeshellarg( realpath($this->entryScript) ));
		if( @$options['output'] == 'json' ){
			array_push($php_command, '-o');
			array_push($php_command, 'json');
		}
		if( @strlen($options['user_agent']) ){
			array_push($php_command, '-u');
			array_push($php_command, escapeshellarg($options['user_agent']));
		}
		array_push($php_command, escapeshellarg($request_path));


		$cmd = implode( ' ', $php_command );

		// コマンドを実行
		ob_start();
		$proc = proc_open($cmd, array(
			0 => array('pipe','r'),
			1 => array('pipe','w'),
			2 => array('pipe','w'),
		), $pipes);
		$io = array();
		foreach($pipes as $idx=>$pipe){
			$io[$idx] = stream_get_contents($pipe);
			fclose($pipe);
		}
		$return_var = proc_close($proc);
		ob_get_clean();

		$bin = $io[1]; // stdout
		if( strlen( $io[2] ) ){
			// $this->error($io[2]); // stderr
		}

		if( @$options['output'] == 'json' ){
			$bin = json_decode($bin);
		}

		return $bin;
	}

}