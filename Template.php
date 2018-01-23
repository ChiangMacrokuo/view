<?php
namespace Macro\View;
use Kernel\Http\Request;
use Kernel\Exception\KernelException;
class Template 
{
    
    /**
     * 模板变量
     * @var array
     */
    private $templateVar = [];
    
    /**
     * 模板文件目录
     * @var string
     */
    public $templateDir = 'templates/';
    
    /**
     * 编译文件目录
     * @var string
     */
    public $compileDir = 'compiles/';
    
    /**
     * 缓存文件目录
     * @var string
     */
    public $cacheDir = 'caches/';
    
    /**
     * 模板文件
     * @var string
     */
    private $templateFile = '';
    
    /**
     * 编译文件
     * @var string
     */
    private $compileFile = '';
    
    /**
     * 缓存文件
     * @var string
     */
    private $cacheFile = '';
    
    /**
     * 缓存开关
     * @var string
     */
    public $cache = true;
    
    /**
     * 生命周期
     * @var integer
     */
    public $lifeTime = 3600;
    
    /**
     * 右标签
     * @var string
     */
    public $rightTag = '{';
    
    /**
     * 左标签
     * @var string
     */
    public $leftTag = '}';
    
    /**
     * 模板文件后缀
     * @var string
     */
    public $templateExt = '.blade.php';
    
    /**
     * 缓存文件后缀
     * @var string
     */
    public $cacheExt = '.html';
    
    /**
     * 构造方法
     */
    public function __construct()
    {
        $this->registerEiVariables();
    }
    
    /**
     * 注册系统变量
     * 支持的系统变量有: session get post cookie request server
     * 支持常量获取: {$sysvar.const }
     */
    protected final function registerEiVariables()
    {
        $this->templateVar['Ei'] = array(
            'get' 	 => Request::get(),
            'post'	 => Request::post(),
            'request'=> Request::request(),
            'cookie' => Request::cookie(),
            'server' => array_change_key_case(Request::server(), CASE_LOWER),
            'session'=> isset($_SESSION) ? $_SESSION : array(),
            'const'  => array_change_key_case(get_defined_constants(true)['user'], CASE_LOWER)
        );
    }
    
    /**
     * 返回解析之后的模板内容
     * @param string $file 模板文件
     */
    public function fetch($file)
    {
        $this->templateFile = $this->templateDir . $file . $this->templateExt;
        if (!file_exists($this->templateFile)){
            throw new KernelException('模板文件' .$this->templateFile. '不存在！');
        }
        $file = str_replace(DIRECTORY_SEPARATOR, '_', $file);
        $this->compileFile = $this->compileDir . md5($file) . '_' . $file . $this->templateExt;
        $this->compile();
        if ($this->cache){
            $this->cacheFile = $this->cacheDir . md5($file) . '_' . $file . $this->cacheExt;
            $this->cache();
        }
        return $this->includeTemplate($this->cache ? $this->cacheFile : $this->compileFile);
    }
    
    /**
     * 生成缓存文件
     * @throws KernelException
     */
    protected function cache()
    {
        if (!file_exists($this->cacheFile) || filemtime($this->compileFile) > filemtime($this->cacheFile)){
            if (!is_dir($this->cacheDir)){
                mkdir($this->cacheDir,0777,true);
            }else if (!is_writable($this->cacheDir)){
                throw new KernelException('缓存目录不可写');
            }
            file_put_contents($this->cacheFile, $this->includeTemplate($this->compileFile));
        }
    }
    
    
    /**
     * 清除指定缓存
     * @param string $file
     */
    public function clearCache($file)
    {
        $file = str_replace(DIRECTORY_SEPARATOR, '_', $file);
        $file = $this->cacheDir . md5($file) . '_' . $file . $this->cacheExt;
        if(file_exists($file)){
            unlink($file);
        }else{
            $files = scandir($this->cacheDir);
            foreach ($files as $filename){
                if(stripos($filename,$file) !== false){
                    unlink($this->cacheDir . $filename);
                }
            }
        }
    }
    
    /**
     * 清除所有缓存
     */
    public function clearAllCache(){
        $files = scandir($this->cacheDir);
        foreach ($files as $filename){
            if ($filename != '.' && $filename != '..'){
                unlink($this->cacheDir . $filename);
            }
        }
    }
    
    /**
     * 表单令牌验证
     */
    public function checkToken()
    {
        $token = S('TOKEN');
        if(isset($token,$_REQUEST['TOKEN'])){
            if(S('TOKEN') != $_REQUEST['TOKEN']){
                $referer = $_SERVER['HTTP_REFERER'];
                header('Refresh: 3;url=' . $referer);
                echo '表单重复提交，请<a href="' . $referer . '">返回</a>后刷新页面再试！';
                exit();
            }
            S('TOKEN', md5(microtime()));
        }
    }
    
    /**
     * 包含一个模板文件
     * @param string $file
     * @return string
     */
    protected function includeTemplate($file)
    {
        ob_start();
        extract($this->templateVar,EXTR_OVERWRITE);
        include $file;
        return ob_get_clean();
    }
    
    /**
     * 返回解析之后的模板内容
     * @param string $file 模板文件
     */
    public function show($file)
    {
        echo $this->fetch($file);
    }
    
    /**
     * 单一赋值、批量赋值
     * @param mixed $name
     * @param mixed $value
     */
    public function assign($name, $value=NULL)
    {
        if(is_array($name)){
            $this->templateVar = array_merge($this->templateVar,$name);
        }else{
            $this->templateVar[$name] = $value;
        }
    }
    
    /**
     * 编译
     * @throws KernelException
     */
    protected function compile()
    {
        if (!file_exists($this->compileFile) || filemtime($this->templateFile) > filemtime($this->compileFile)){
            if(!is_dir($this->compileDir)){
                mkdir($this->compileDir,0777,true);
            }else if(!is_writable($this->compileDir)){
                throw new KernelException('编译目录不可写');
            }
            $templateContent = file_get_contents($this->templateFile);
            $templateContent = $this->parse($templateContent);
            file_put_contents($this->compileFile,$templateContent);
        }
    }
    
    /**
     * 取实例
     * @param unknown $className
     * @return unknown
     */
    public function instance($className)
    {
        $className = __NAMESPACE__.'\\'.$className;
        static $clsContainer = array();
        if(!isset($clsContainer[$className])){
            $clsContainer[$className] = new $className($this);
        }
        return $clsContainer[$className];
    }
    
    /**
     * 解析
     */
    protected function parse(&$content)
    {
        $instance = $this->instance('InheritTagLib');
        $content = $instance->parse($content);
        $content = $instance->restoreBlock($content);
        $content = $this->parseInclude($content);
        $content = $this->parseLiteral($content);
        $content = $this->instance('TagLib')->parse($content);
        $content = $this->parseVariable($content);
        $content = $this->parseConst($content);
        $content = $this->parseFunction($content);
        $content = $this->parsePhpTag($content);
        $content = $this->parseWhiteSpace($content);
        $content = $this->parseComment($content);
        $content = $this->restoreLiteral($content);
        return $content;
    }

    /**
     * 解析单行注释和多行注释标签
     * @param string $data 模板内容
     * @return string
     */ 
    public function parseComment(&$data)
    {
        $leftTag = preg_quote($this->leftTag);
        $rightTag = preg_quote($this->rightTag);
        $patternOne = sprintf('!%s\s*//(.*?)\s*%s!i', $leftTag, $rightTag, $rightTag);
        $patternMore = sprintf('!%s\s*/\*(.*?)\*/\s*%s!i', $leftTag, $rightTag);
        return preg_replace(array($patternOne, $patternMore), array('<?php //$1?>','<?php /*$1*/?>'), $data);
    }
    
    /**
     * 解析函数调用标签
     * @param string $data 模板内容
     * @return string
     */
    public function parseFunction(&$data)
    {
        $leftTag = preg_quote($this->leftTag);
        $rightTag = preg_quote($this->rightTag);
        $pattern = sprintf('!%s\s*:\s*([a-zA-Z_]\w*)\s*\(([^\r\n%s]*?)\)\s*%s!i', $leftTag, $rightTag, $rightTag);
        return preg_replace($pattern, '<?php echo \1(\2);?>', $data);
    }
    
    /**
     * 去除多余的php标签
     * @param string $data 模板内容
     * @return string
     */
    protected function parsePhpTag(&$data)
    {
        return preg_replace('!\?>\s*<\?php!i', '', $data);
    }
    
    /**
     * 去除空行
     * @param string $data 模板内容
     * @return string
     */
    protected function parseWhiteSpace(&$data)
    {
        return preg_replace('!^\s*\r?\n!m', '', $data);
    }
    
    /**
     * 解析变量名称部分
     * @param string $partition 名称部分
     * @return string
     */
    public function parseVariablePartition($partition)
    {
        if (strpos($partition, '.') !== false){
            $variablePartition = array_map('trim', explode('.', $partition));
            $name = array_shift($variablePartition);
            $variableName = '$' . $name;
            foreach ($variablePartition as $variable){
                $variableName .= "['{$variable}']";
            }
            return $variableName;
        }else {
            return '$' . $partition;
        }
    }
    
    /**
     * 解析变量函数部分
     * @param string $partition 函数部分
     * @return string
     */
    public function parseFunctionPartition($functionPartition)
    {
        if (!isset($functionPartition[1])){
            $functionPartition[1] = '###';
        }
        return $functionPartition[0] . '(' . $functionPartition[1] . ')';
    }
    
    /**
     * 解析变量标签
     * @param unknown $data
     * @return mixed
     */
    public function parseVariable(&$data)
    {
        $leftTag = preg_quote($this->leftTag);
        $rightTag = preg_quote($this->rightTag);
        $pattern = sprintf('!%s\s*\$([^\r\n%s]*?)\s*%s!i', $leftTag, $rightTag, $rightTag);
        return preg_replace_callback($pattern, function($matches){
            //P($matches);
            if (false !== ($position = strpos($matches[1], '?'))){
                $array = preg_split('/([!=]={1,2}|(?<!-)[><]={0,1})/', substr($matches[1], 0, $position), 2, PREG_SPLIT_DELIM_CAPTURE);
                $variableFlag = substr($array[0], 0, 1);
                if ($variableFlag == '$'){
                    $variableName = $this->parseVariable($array[0]);
                }else {
                    $variableName = $this->parseVariablePartition($array[0]);
                }
                $string  = trim(substr($matches[1], $position+1));
                $first = substr($string, 0,1);
                if(isset($array[1])){
                    $variableConditionFlag = substr($array[2], 0, 1);
                    if ($variableConditionFlag == '$'){
                        $variableCondition = $this->parseVariable($array[2]);
                    }elseif (false !== strpos($array[2], '.')){
                        $variableCondition = $this->parseVariablePartition($array[2]);
                    }else {
                        $variableCondition = $array[2];
                    }
                    $variableName .= $array[1] . $variableCondition;
                }
                switch ($first){
                    case '?':
                        $code = '<?php echo isset(' . $variableName . ') ? ' . $variableName . ':' . substr($string,1) . ';?>';
                        break;
                    case '=':
                        $code = '<?php if(empty(' . $variableName . ')){ echo ' . substr($string, 1) . ';}?>';
                        break;
                    case ':':
                        $code = '<?php echo (!empty(' . $variableName . ')) ? ' . $variableName . ':' .substr($string, 1) . ';?>';
                        break;
                    default:
                        $code = '<?php echo (!empty(' . $variableName . ')) ? ' . $string .';?>';
                }
                return $code;
            }else {
                $variableArr = array_map('trim', explode('|', $matches[1]));
                $variableArr = array_filter($variableArr);
                $varPartition = array_shift($variableArr);
                $variableCode = $this->parseVariablePartition($varPartition);
                if (empty($variableArr)){
                    return "<?php echo {$variableCode};?>";
                }else {
                    $code = '';
                    foreach ($variableArr as $partition){
                        $functionPartition = array_map('trim', explode('=', $partition));
                        if ($functionPartition[0] == 'default'){
                            $code = $variableCode .' = isset(' . $variableCode . ') && (' . $variableCode . ' !== \'\') ? ' . $variableCode. ' : ' .$functionPartition[1] . ';';
                            continue;
                        }
                        $functionCode = $this->parseFunctionPartition($functionPartition);
                        $variableCode = str_replace('###', $variableCode, $functionCode);
                    }
                    return "<?php {$code} echo {$variableCode};?>";
                }
            }
        }, $data);
    }
    
    public function parseTagAttributionVariable($attribution)
    {
        $flag = substr($attribution, 0, 1);
        if (preg_match('/[a-zA-Z_]\w*/', $attribution) && defined($attribution)){
            return $attribution;
        }else {
            if ($flag == '$'){
                $attribution = substr($attribution,1);
            }
            return $code = $this->parseVariablePartition($attribution);
        }
    }
    
    /**
     * 解析常量
     * @param string $data 模板内容
     * @return string
     */
    protected function parseConst(&$data)
    {
        $pattern = '!(__)[A-Z_]+\1!';
        preg_match_all($pattern, $data,$match,PREG_SET_ORDER);
        return preg_replace($pattern, "<?php echo $0;?>", $data);
    }
    
    /**
     * 解析include包含标签
     * @example {include file="" /}
     * @param string $data 模板内容
     * @return string
     */
    protected function parseInclude(&$data)
    {
        $leftTag = preg_quote($this->leftTag);
        $rightTag = preg_quote($this->rightTag);
        $pattern = sprintf('!%s\s*include\s+file\s*=\s*(\"|\')([^\r\n%s]*?)\1\s*/\s*%s!i', $leftTag, $rightTag, $rightTag);
        return preg_replace_callback($pattern, array($this, 'pregIncludeCallback'), $data);
    }
    
    /**
     * 解析include包含标签回调
     * @param array $match 匹配项
     * @throws KernelException
     * @return string
     */
    protected function pregIncludeCallback($match)
    {
        $file = $this->templateDir . str_replace('/', DIRECTORY_SEPARATOR, $match[2]) . $this->templateExt;
        if (file_exists($file)){
            $data = file_get_contents($file);
            return $this->loopInclude($data);
        }else{
            throw new KernelException('include标签所需包含文件' .$file. '不存在！');
        }
    }
    
    /**
     * 递归解析include包含标签
     * @param string $data 板内容
     * @return string
     */
    protected function loopInclude(&$data)
    {
        $leftTag = preg_quote($this->leftTag);
        $rightTag = preg_quote($this->rightTag);
        $pattern = sprintf('!%s\s*include\s+file\s*=\s*(\"|\')([^\r\n%s]*?)\1\s*/\s*%s!i', $leftTag, $rightTag, $rightTag);
        if (preg_match_all($pattern, $data, $matches,PREG_SET_ORDER)){
            if (!empty($matches)){
                foreach ($matches as $match){
                    $data = str_replace($match[0], $this->getTemplateContent($match[2]), $data);
                    $this->loopInclude($data);
                }
            }
        }
        return $data;
    }
    
    /**
     * 获取模板文件内容
     * @param string $file 文件名
     * @throws KernelException
     * @return string
     */
    protected function getTemplateContent($file)
    {
        $templateFile = $this->templateDir . str_replace('/', DIRECTORY_SEPARATOR, $file) . $this->templateExt;
        if (file_exists($templateFile)){
            return file_get_contents($templateFile);
        }else {
            throw new KernelException('include标签所需包含文件' .$templateFile. '不存在！');
        }
    }

    /**
     * 解析literal过滤标签
     * @example {literal}{/literal}
     * @param string $data 模板内容
     * @return string
     */
    protected function parseLiteral(&$data)
    {
        $leftTag = preg_quote($this->leftTag);
        $rightTag = preg_quote($this->rightTag);
        $pattern = sprintf('!%s\s*literal\s*%s(.*?)%s\s*/literal\s*%s!is', $leftTag, $rightTag, $leftTag, $rightTag);
        return preg_replace_callback($pattern, array($this, 'pregLiteralCallback'), $data);
    }
    
    /**
     * 解析literal过滤标签回调
     * @param array $match 匹配项
     * @return string
     */
    protected function pregLiteralCallback($match)
    {
        return str_replace(array($this->leftTag, $this->rightTag, '$'), array('LITERAL_LEFTTAG', 'LITERAL_RIGHTTAG', 'LITERAL_DOLLAR'), $match[1]);
    }
    
    /**
     * 还原literal过滤标签内容
     * @param string $data 模板内容
     * @return string
     */
    protected function restoreLiteral(&$data)
    {
        return str_replace(array('LITERAL_LEFTTAG', 'LITERAL_RIGHTTAG', 'LITERAL_DOLLAR'), array($this->leftTag, $this->rightTag, '$'), $data);
    }

}