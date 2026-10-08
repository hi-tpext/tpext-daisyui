<?php

namespace tpext\builder\common;

use tpext\builder\common\Module;
use tpext\think\View;

class Layer extends Widget
{
    /**
     * 关闭弹窗操作对应的视图对象
     *
     * @var View|null
     */
    protected $viewShow;

    /**
     * 获取视图对象
     *
     * @return View|null
     */
    public function getViewShow()
    {
        return $this->viewShow;
    }

    /**
     * 关闭弹窗（ajax时返回json指令，否则输出关闭页）
     *
     * @param boolean $success
     * @param string $msg
     * @return json|View
     */
    public function close($success = true, $msg = '操作成功')
    {
        if (request()->isAjax()) {
            return json([
                'code' => $success ? 1 : 0,
                'msg' => $msg,
                'layer_close' => 1,
            ]);
        }

        $view = Module::getInstance()->getViewsPath() . 'layer' . DIRECTORY_SEPARATOR . 'close.html';

        $vars = [
            'success' => $success ? 1 : 0,
            'msg' => $msg,
        ];

        $this->viewShow = new View($view);

        $this->viewShow->assign($vars);

        return $this->viewShow;
    }

    /**
     * 关闭弹窗并跳转到指定地址（ajax时返回json指令，否则输出页面）
     *
     * @param boolean $success
     * @param string $msg
     * @param string $url
     * @return json|View
     */
    public function closeGo($success = true, $msg = '操作成功', $url = '')
    {
        if (request()->isAjax()) {
            return json([
                'code' => $success ? 1 : 0,
                'msg' => $msg,
                'layer_close_go' => (string) $url,
            ]);
        }

        $view = Module::getInstance()->getViewsPath() . 'layer' . DIRECTORY_SEPARATOR . 'closego.html';

        $vars = [
            'success' => $success ? 1 : 0,
            'msg' => $msg,
            'url' => (string) $url,
        ];

        $this->viewShow = new View($view);

        $this->viewShow->assign($vars);

        return $this->viewShow;
    }

    /**
     * 关闭弹窗并刷新父页（ajax时返回json指令，否则输出页面）
     *
     * @param boolean $success
     * @param string $msg
     * @return json|View
     */
    public function closeRefresh($success = true, $msg = '操作成功')
    {
        if (request()->isAjax()) {
            return json([
                'code' => $success ? 1 : 0,
                'msg' => $msg,
                'layer_close_refresh' => 1,
            ]);
        }

        $view = Module::getInstance()->getViewsPath() . 'layer' . DIRECTORY_SEPARATOR . 'closerefresh.html';

        $vars = [
            'success' => $success ? 1 : 0,
            'msg' => $msg,
        ];

        $this->viewShow = new View($view);

        $this->viewShow->assign($vars);

        return $this->viewShow;
    }
}
