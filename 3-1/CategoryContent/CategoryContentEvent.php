<?php
/*
* This file is part of EC-CUBE
*
* Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
* http://www.lockon.co.jp/
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Plugin\CategoryContent;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class CategoryContentEvent
{
    /**
     * @var \Eccube\Application
     */
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * 商品一覧画面に、カテゴリコンテンツの内容を表示
     *
     * @param FilterResponseEvent $event
     */
    public function onRenderProductListBefore(FilterResponseEvent $event)
    {   
        var_dump('Hello Plugin！');
        exit();
    }

    // カテゴリ登録画面表示時
    public function onRenderAdminProductCategoryNewBefore(FilterResponseEvent $event)
    {
        $app = $this->app;
        $request = $event->getRequest();
        $response = $event->getResponse();

        $html = $this->getHtmlAdminProductCategory($request, $response);

        $response->setContent($html);
        $event->setResponse($response);
    }

    // カテゴリ登録画面にて保存ボタン押下時の後処理
    public function onRenderAdminProductCategoryNewAfter()
    {
        $app = $this->app;

        if ('POST' !== $app['request']->getMethod()) {
            return;
        }

        $maxId = $app['eccube.repository.category']
                    ->createQueryBuilder('c')
                    ->select('MAX(c.id)')
                    ->getQuery()
                    ->getSingleScalarResult();

        $category = $app['eccube.repository.category']->find($maxId);

        $form = $app['form.factory']->createBuilder('admin_category')->getForm();

        $CategoryContent = new \Plugin\CategoryContent\Entity\CategoryContent();

        if ('POST' === $app['request']->getMethod()) {
            $form->handleRequest($app['request']);
            if ($form->isValid()) {
                $CategoryContent
                    ->setId($maxId)
                    ->setContent($form['content']->getData());
                $app['orm.em']->persist($CategoryContent);
                $app['orm.em']->flush();
            }
        }

    }

    // カテゴリ編集画面表示時        
    public function onRenderAdminProductCategoryEditBefore(FilterResponseEvent $event)
    {
        $app = $this->app;

        $request = $event->getRequest();
        $response = $event->getResponse();

        $id = $request->attributes->get('id');

        $CategoryContent = null;

        if ($id) {
            $CategoryContent = $app['category_content.repository.category_content']
                ->find($id);
        }

        if (is_null($CategoryContent)) {
            $CategoryContent = new \Plugin\CategoryContent\Entity\CategoryContent();
        }

        // DomCrawlerにHTMLを食わせる
        $html = $response->getContent();
        $crawler = new Crawler($html);

        $form = $app['form.factory']
            ->createBuilder('admin_category')
            ->getForm();
        $form['content']->setData($CategoryContent->getContent());
        $form->handleRequest($request);

        $twig = $app->renderView(
            'CategoryContent/Resource/template/category.twig',
            array('form' => $form->createView())
        );

        $oldCrawler = $crawler
            ->filter('form')
            ->first();

        // DomCrawlerからHTMLを吐き出す
        $html = $crawler->html();
        $oldHtml = '';
        $newHtml = '';

        if (count($oldCrawler) > 0) {
            $oldHtml = $oldCrawler->html();
            $newHtml = $oldHtml.$twig;
        }

        $html = str_replace($oldHtml, $newHtml, $html);
        $response->setContent($html);
        $event->setResponse($response);

    }

    // カテゴリ登録画面にて保存ボタン押下時の後処理
    public function onAdminProductCategoryEditAfter()
    {
        $app = $this->app;

        if ('POST' !== $app['request']->getMethod()) {
            return;
        }

        $id = $app['request']->attributes->get('id');

        $form = $app['form.factory']
            ->createBuilder('admin_category')
            ->getForm();

        $CategoryContent = $app['category_content.repository.category_content']
            ->find($id);

        if (is_null($CategoryContent)) {
            $CategoryContent = new \Plugin\CategoryContent\Entity\CategoryContent();
        }

        if ('POST' === $app['request']->getMethod()) {
            $form->handleRequest($app['request']);
            if ($form->isValid()) {
                $CategoryContent
                    ->setId($id)
                    ->setContent($form['content']->getData());
                $app['orm.em']->persist($CategoryContent);
                $app['orm.em']->flush();
            }
        }
    }


    /**
     *
     * @param Request $request
     * @param Response $response
     * @return string
     */
    private function getHtmlAdminProductCategory(Request $request, Response $response)
    {
        $crawler = new Crawler($response->getContent());
        $html = $this->getHtml($crawler);

        $CategoryContent = new \Plugin\CategoryContent\Entity\CategoryContent();
        $form = $this->app['form.factory']->createBuilder('admin_category')->getForm();
        $form['content']->setData($CategoryContent->getContent());

        $form->handleRequest($request);

        $parts = $this->app->renderView('CategoryContent/Resource/template/category.twig', array('form' => $form->createView()));

        try {
            $oldHtml = $crawler->filter('form')->first()->html();
            $newHtml = $oldHtml . $parts;
            $html = str_replace($oldHtml, $newHtml, $html);

        } catch (\InvalidArgumentException $e) {
        }

        return html_entity_decode($html, ENT_NOQUOTES, 'UTF-8');
    }

    /**
     * 解析用HTMLを取得
     *
     * @param Crawler $crawler
     * @return string
     */
    private function getHtml(Crawler $crawler)
    {
        $html = '';
        foreach ($crawler as $domElement) {
            $domElement->ownerDocument->formatOutput = true;
            $html .= $domElement->ownerDocument->saveHTML();
        }
        return html_entity_decode($html, ENT_NOQUOTES, 'UTF-8');
    }


}
