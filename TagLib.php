<?php
namespace Macro\View;
use Kernel\Exception\KernelException;

class TagLib extends AbstractTagLib
{
    
    /**
     * 标签数组定义
     * @var array
     */
    protected $tagArr = [
        'volist'    =>['attr' =>'name,item,key,index'],
        'switch'    =>['attr'=>'name'],
        'case'      =>['attr'=>'value'],
        'default'   =>[],
        'if'        =>['attr'=>'condition'],
        'elseif'    =>['attr'=>'condition'],
        'else'      =>[],
        'foreach'   =>[],
        'for'       =>['attr'=>'name,start,stop,step,comparison'],
        'in'        =>['attr'=>'name,value'],
        'between'   =>['attr'=>'name,value'],
        'assign'    =>['attr'=>'name,value'],
        'php'       =>[],
        'token'     =>[],
        'notempty'  =>['attr'=>'name'],
        'empty'     =>['attr'=>'name'],
        'isset'     =>['attr'=>'name'],
        'notisset'  =>['attr'=>'name'],
        'eq'        =>['attr'=>'name,value'],
        'neq'       =>['attr'=>'name,value']
    ];
    
    /**
     * 当前解析的标签名
     * @var string
     */
    protected $currentTagName = '';
    
    /**
     * 当前标签属性
     * @var array
     */
    protected $attributionArr = [];
    
    /**
     * 解析入口
     * {@inheritDoc}
     * @see \Kernel\Template\AbstractTagLib::parse()
     */
    public function parse(&$content)
    {
        $leftTag = preg_quote($this->template->leftTag);
        $rightTag = preg_quote($this->template->rightTag);
        $pattern = sprintf('!%s(/?)([a-zA-Z_]\w*)([^\r\n%s]*?)(/?)\s*%s!i', $leftTag, $rightTag, $rightTag);
        return preg_replace_callback($pattern, array($this, 'pregCallback'), $content);
    }
    
    /**
     * 解析回调
     * @param array $match 捕获元素数组
     * @throws KernelException
     * @return unknown
     */
    protected function pregCallback($match)
    {
        $this->currentTagName = $tagName = $match[2];
        if (isset($this->tagArr[$tagName])){
            if ($match[1] == '/'){
                $callbackMethod = 'parseEnd' . ucfirst($tagName);
                $content = $this->$callbackMethod();
                array_pop($this->attributionArr[$tagName]);
            }else {
                $callbackMethod = 'parseStart' . ucfirst($tagName);
                $attributionArr = $this->parseAttribute($match[3]);
                $this->attributionArr[$tagName][] = $attributionArr;
                $content = $this->$callbackMethod($attributionArr);
            }
            return $content;
        }else {
            throw new KernelException("模板引擎不支持{$tagName}标签！");
        }
    }
    
    /**
     * 获取当前正在被解析标签的所有属性
     * @return mixed
     */
    protected function getCurrentTagAttribution()
    {
        $count = count($this->attributionArr[$this->currentTagName]);
        return $this->attributionArr[$this->currentTagName][$count-1];
    }
    
    /**
     * 获取当前正在被解析标签的指定属性
     * @param string $attributionName
     * @return mixed|NULL
     */
    protected function getAttribution($attributionName)
    {
        $attribution = $this->getCurrentTagAttribution();
        if (isset($attribution[$attributionName])){
            return $attribution[$attributionName];
        }
        return null;
    }
    
    /**
     * 给当前标签设置属性
     * @param string $attributionName
     * @param mixed $attributionValue
     */
    protected function setAttribution($attributionName, $attributionValue)
    {
        $count = count($this->attributionArr[$this->currentTagName]);
        $this->attributionArr[$this->currentTagName][$count-1][$attributionName] = $attributionValue;
    }
    
    /**
     * 替换运算符
     * @param string $operator 操作符
     * @return string
     */
    protected function replaceOperator($operator)
    {
        $pattern = [
            '/\beq\b/i',
            '/\blt\b/i',
            '/\bgt\b/i',
            '/\ble\b/i',
            '/\bge\b/i',
            '/\bnot\b/i',
            '/\bneq\b/i',
            '/\bheq\b/i',
            '/\bhneq\b/i',
            '/\band\b/i',
            '/\bor\b/i'
        ];
        $replace = [
            '==',
            '<',
            '>',
            '<=',
            '>=',
            '!',
            '!=',
            '===',
            '!==',
            '&&',
            '||'
        ];
        return preg_replace($pattern, $replace, $operator);
    }
    
    /**
     * 解析PHP块标签，可以在模板中直接书写 php 代码
     * @example {php} PHP Code {/php}
     * @param array $attribution 标签属性
     * @return string
     */
    public function parseStartPhp($attribution)
    {
        return "<?php";
    }
    
    /**
     * 解析PHP块标签
     * @return string
     */
    public function parseEndPhp()
    {
        return ' ?>';
    }
    
    /**
     * 解析notempty开始标签
     * {notempty name=""}{/notempty}
     * @param array $attribution 标签属性
     * @return string
     */
    protected function parseStartNotEmpty($attribution)
    {
        $variableName = $attribution[$this->tagArr[$this->currentTagName]['attr']];
        $variableName = $this->template->parseTagAttributionVariable($variableName);
        return "<?php if(!empty({$variableName})){ ?>";
    }
    
    /**
     * 解析notempty结束标签
     * @return string
     */
    protected function parseEndNotEmpty()
    {
        return '<?php }?>';
    }
    
    /**
     * 解析empty开始标签
     * {empty name=""}{/empty}
     * @param array $attribution 标签属性
     * @return string
     */
    protected function parseStartEmpty($attribution)
    {
        $variableName = $attribution[$this->tagArr[$this->currentTagName]['attr']];
        $variableName = $this->template->parseTagAttributionVariable($variableName);
        return "<?php if(!empty({$variableName})){ ?>";
    }
    
    /**
     * 解析empty结束标签
     * @return string
     */
    protected function parseEndEmpty()
    {
        return '<?php }?>';
    }
    
    /**
     * 解析eq开始标签
     * @example {eq name="" value=""}{/eq}
     * @param array $attribution 标签属性
     * @return string
     */
    protected function parseStartEq($attribution)
    {
        $name = $attribution['name'];
        $variableName = $this->template->parseTagAttributionVariable($name);
        $value = trim($attribution['value']);
        return "<?php if({$variableName} == {$value}){ ?>";
    }
    
    /**
     * 解析neq结束标签
     * @return string
     */
    protected function parseEndEq()
    {
        return '<?php }?>';
    }
    
    /**
     * 解析in开始标签
     * @example {in name="" value=""}{/in}
     * @param array $attribution 标签属性
     * @return string
     */
    protected function parseStartIn($attribution)
    {
        $name = $attribution['name'];
        $variableName = $this->template->parseTagAttributionVariable($name);
        $value = array_map('trim', explode(',', $attribution['value']));
        $arrayString = var_export($value,true);
        return "<?php if(in_array({$variableName},{$arrayString})){?>";
    }
    
    /**
     * 解析in结束标签
     * @return string
     */
    protected function parseEndIn()
    {
        return "<?php }?>";
    }
    
    /**
     * 解析between开始标签
     * @example {between name="" value=""}{/between}
     * @param array $attribution 标签属性
     * @throws KernelException
     * @return string
     */
    protected function parseStartBetween($attribution)
    {
        $name = $attribution['name'];
        $variableName = $this->template->parseTagAttributionVariable($name);
        $value = explode(',', $attribution['value']);
        $count = count($value);
        if ($count != 2){
            throw new KernelException('between标签的value属性语法错误！');
        }
        return "<?php if({$variableName} >= {$value[0]} && {$variableName} <= {$value[1]}){?>";
    }
    
    /**
     * 解析between结束标签
     * @return string
     */
    protected function parseEndBetween()
    {
        return "<?php }?>";
    }
    
    /**
     * 解析if开始标签
     * @example {if condition=""}{/if}
     * @param array $attribution 标签属性
     * @return string
     */
    protected function parseStartIf($attribution)
    {
        $content = $this->replaceOperator($attribution['condition']);
        return "<?php if($content){ ?>";
    }
    
    /**
     * 解析elseif开始标签
     * @example {elseif condition="" /}或者{elseif condition=""}{/elesif}
     * @param array $attribution 标签属性
     * @return string
     */
    protected function parseStartELseIf($attribution)
    {
        $content = $this->replaceOperator($attribution['condition']);
        return "<?php elseif($content){ ?>";
    }
    
    /**
     * 解析elseif结束标签
     * @return string
     */
    protected function parseEndElseIf()
    {
        return '';
    }
    
    /**
     * 解析if结束标签
     * @return string
     */
    protected function parseEndIf()
    {
        return "<?php }?>";
    }
    
    /**
     * 解析else开始标签
     * @example {else}{/else}或者{else/}
     * @return string
     */
    protected function parseStartElse($attribution)
    {
        return "<?php }else{ ?>";
    }
    
    /**
     * 解析else结束标签
     * @return string
     */
    protected function parseEndElse()
    {
        return '';
    }
    
    /**
     * 解析volist开始标签
     * @example {volist name="" item="" key="" index=""}{/volist}
     * @param array $attribution 标签属性
     * @return string
     */
    protected function parseStartVolist($attribution)
    {
        $name = $attribution['name'];
        $variableName = $this->template->parseTagAttributionVariable($name);
        $item = $attribution['item'];
        $key  = isset($attribution['key']) ? $attribution['key'] : 'key';
        $index = isset($attribution['index']) ? $attribution['index'] : 'index';
        $this->setAttribution('index', $index);
        return "<?php \$$index=1;foreach({$variableName} as \${$key}=>\${$item}){ ?>";
    }
    
    /**
     * 解析volist结束标签
     * @return string
     */
    protected function parseEndVolist()
    {
        $index = $this->getAttribution('index');
        return "<?php \${$index}++;}?>";
        
    }
    
    /**
     * 解析foreach开始标签
     * @example {foreach $var as $val}{/foreach}或者{foreach $var as $key=>$val}{/foreach}
     * @param array $attribution 标签属性
     * @return string
     */
    protected function parseStartForeach($attribution)
    {
        $attributionKey = array_keys($attribution);
        $value = array_pop($attributionKey);
        $as = array_pop($attributionKey);
        $key = '';
        if ($as != 'as'){
            $key = $as;
            $as =array_pop($attributionKey);
        }
        $variable = array_shift($attributionKey);
        $code = '';
        if (!empty($attributionKey)){
            foreach ($attributionKey as $attr){
                $code .= "['{$attr}']";
            }
        }
        $variable .= $code;
        if ($key){
            $str = "<?php foreach(\${$variable} {$as} \${$key}=>\${$value}){ ?>";
        }else {
            $str = "<?php foreach(\${$variable} {$as} \${$value}){ ?>";
        }
        return $str;
    }
    
    /**
     * 解析foreach结束标签
     * @return string
     */
    protected function parseEndForeach()
    {
        return "<?php }?>";
    }
    
    /**
     * 解析for开始标签
     * @param array $attribution 标签属性
     * @return string
     */
    protected function parseStartFor($attribution)
    {
        $variable = $attribution['name'];
        $start = $attribution['start'];
        $stop = $this->template->parseVariablePartition($attribution['stop']);
        $step = isset($attribution['step']) ? $attribution['step'] : 1;
        $comparsion = isset($attribution['comparison']) ? $this->replaceOperator($attribution['comparison']) : '<';
        return "<?php for(\${$variable} = {$start};\${$variable} {$comparsion} {$stop};\$$variable += $step){ ?>";
    }
    
    /**
     * 解析for结束标签
     * @return string
     */
    protected function parseEndFor()
    {
        return "<?php }?>";
    }
    
    /**
     * 解析switch开始标签
     * @example
     * {switch name=""}
     * 	 {case value=""}{/case}
     *   {case value=""}{/case}
     *   {default}
     *   {/default}
     * {/switch}
     * @param array $attribution 标签属性
     * @return string
     */
    protected function parseStartSwitch($attribution)
    {
        $name = $attribution['name'];
        $variableName = $this->template->parseTagAttributionVariable($name);
        return "<?php switch({$variableName}){ default:?>";
    }
    
    /**
     * 解析switch结束标签
     * @return string
     */
    protected function parseEndSwitch()
    {
        return "<?php break;}?>";
    }
    
    /**
     * 解析case开始标签
     * @example {case value=""}{/case}
     * @param array $attribution 标签属性
     * @return string
     */
    protected function parseStartCase($attribution)
    {
        $value = $attribution['value'];
        $variableName = $this->template->parseTagAttributionVariable($value);
        return '<?php break;case "'.$value.'":?>';
    }
    
    /**
     * 解析case结束标签
     * @return string
     */
    protected function parseEndCase()
    {
        return "";
    }
    
    /**
     * 模板文件中给变量赋值, 支持数字、字符串、布尔值
     * @example {assign name="" value=""}
     * @param array $attribution 标签属性
     * @return string
     */
    protected function parseStartAssign($attribution)
    {
        $value = $attribution['value'];
        if (is_numeric($value) || $value == 'true' || $value == 'false' ){
            return "<?php \${$attribution['name']} = {$value}; ?>";
        }elseif (is_string($value)) {
            $value = addslashes($value);
            return "<?php \${$attribution['name']} = '{$value}'";
        }
        return '';
    }
    
    /**
     * 表单令牌, 防止表单重复提交
     * @example {token /}
     * @param array $attribution 标签属性
     * @return string
     */
    protected function parseStartToken($attribution)
    {
        if (function_exists('S')){
            $key = md5(microtime() . rand(0, 100000));
            S('TOKEN', $key);
            return '<input type="hidden" name="TOKEN" value="'.$key.'" />';
        }
        return '';
    }
}