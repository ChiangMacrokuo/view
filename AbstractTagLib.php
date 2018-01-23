<?php
namespace Macro\View;
abstract class AbstractTagLib 
{
    /**
     * 模板引擎对象
     * @var object
     */
    protected $template = null;
    
    /**
     * 初始化模板引擎
     */
    public function __construct(Template $template = null)
    {
        if (!is_null($template)){
            $this->template = $template;
        }
    }
    
    /**
     * 申明编译方法的原型
     * @param string $content
     */
    public abstract function parse(& $content);
    
    /**
     * 解析标签属性
     * @param string $attribution
     * @return array
     */
    public function parseAttribute($attribution)
    {
        $attributeArr = [];
        $pattern = '/([a-zA-Z_]\w*)(?:\s*=\s*(\"|\')(.*?)\2)?/';
        if (preg_match_all($pattern, $attribution, $matches,PREG_SET_ORDER)){
            
            foreach ($matches as $match){
                $attributeArr[$match[1]] = isset($match[3]) ? $match[3] : true;
            }
        }
        return $attributeArr;
    }
}