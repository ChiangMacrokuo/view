<?php
namespace Macro\View;
use Kernel\Exception\KernelException;

class InheritTagLib extends AbstractTagLib
{
    /**
     * 标签数组定义
     * @var array
     */
    protected $tagArr = [
        'extend'  =>['attr'=>'parent'],
        'block'   =>['attr'=>'name'],
    ];
    
    /**
     * 当前解析的标签名
     * @var string
     */
    protected $currentTagName = '';    
    
    /**
     * 模板继承文件
     * @var string
     */
    protected $extendFile = null;
    
    /**
     * block数据
     * @var array
     */
    protected $blockData = [];  
    
    /**
     * 解析入口
     * {@inheritDoc}
     * @see \Kernel\Template\AbstractTagLib::parse()
     */
    public function parse(&$content)
    {
        $leftTag = preg_quote($this->template->leftTag);
        $rightTag = preg_quote($this->template->rightTag);
        foreach ($this->tagArr as $tagName => $attribution){
            $this->currentTagName = $tagName;
            $pattern = sprintf('!%s\s*%s([^\r\n%s]*?)(?:/\s*%s|%s(.*?)%s/%s\s*%s)!is', $leftTag, $tagName, $rightTag, $rightTag, $rightTag, $leftTag, $tagName, $rightTag);
            $content = preg_replace_callback($pattern, array($this, 'pregCallback'), $content);
        }
        return $content;
    }
    
    /**
     * 解析回调
     * @param array $matches 捕获元素数组
     * @return string
     */
    protected function pregCallback($match)
    {
        $attribution = $match[1];
        $content = isset($match[2]) ? $match[2] : '';
        $attributionArr = $this->parseAttribute($attribution);
        $attributionName = join(',', array_keys($attributionArr));
        if (strtolower($attributionName) !== $this->tagArr[$this->currentTagName]['attr']){
            throw new KernelException("标签{$this->currentTagName}的属性名称错误！");
        }
        $callbackMethod = 'parse' . ucfirst($this->currentTagName);
        return $this->$callbackMethod($attributionArr, $content);
    }
    
    /**
     * 解析extend继承标签
     * @example {extend parent="" /}
     * @param array $attribution 标签属性
     * @param string $content 模板内容
     * @return string
     */
    protected function parseExtend($attribution, $content)
    {
        $extendFile = $this->template->templateDir . str_replace('/', DIRECTORY_SEPARATOR, $attribution[$this->tagArr[$this->currentTagName]['attr']]) . $this->template->templateExt;
        if (file_exists($extendFile)){
            $this->extendFile = $extendFile;
            return file_get_contents($extendFile);
        }
        return '';
    }
    
    /**
     * 解析block块标签
     * @example {block name=""}{/block}
     * @param array $attribution 标签属性
     * @param string $content 模板内容
     * @return string
     */
    protected function parseBlock($attribution, $content)
    {
        if (!$this->extendFile){
            return "<?php echo \"{$content}\"; ?>";
        }else {
            if (isset($this->blockData[$attribution[$this->tagArr[$this->currentTagName]['attr']]])){
                $this->blockData[$attribution[$this->tagArr[$this->currentTagName]['attr']]] = $content;
                return '';
            }else {
                $this->blockData[$attribution[$this->tagArr[$this->currentTagName]['attr']]] = $content;
                return 'BLOCK_' . strtoupper($attribution[$this->tagArr[$this->currentTagName]['attr']]) . '_CONTENT';
            }
        }
    }
    
    /**
     * 还原 block块标签内容
     * @param string $content 模板内容
     * @return mixed
     */
    public function restoreBlock(&$content)
    {
        if (!empty($this->blockData)){
            foreach ($this->blockData as $name => $block){
                $content = str_replace('BLOCK_' . strtoupper($name) . '_CONTENT', $block, $content);
            }
        }
        return $content;
    }
}