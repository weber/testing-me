<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

// Собираем все последние новости
$app->get('/news/latest', function (Request $req, Response $res, array $args) {
    $DOMEN_NAME_MOBILE = 'https://m.lenta.ru' . DIRECTORY_SEPARATOR;
    $DOMEN_NAME = 'https://lenta.ru' . DIRECTORY_SEPARATOR;

    $data = getDataLatestNews ($this->dom, $DOMEN_NAME, $DOMEN_NAME_MOBILE);

    return $res->withJSON(
        $data,
        200,
        JSON_UNESCAPED_UNICODE
    );
});
$app->get('/news/[{reload}]', function (Request $req, Response $res, array $args) {

    // Если указан флаг reload обновляем кэш по умолчанию берм с кеша
    $reload = ($args['reload'] === 'reload') ? true : false;

    $return = getDataNews ($this, $reload);

    return $res->withJSON(
        $return,
        200,
        JSON_UNESCAPED_UNICODE
    );
});

// Выбираем новость по ID
$app->get('/news/{id}/[{reload}]', function (Request $req, Response $res, array $args) {

    // Если указан флаг reload обновляем кэш по умолчанию берм с кеша
    $reload = ($args['reload'] === 'reload') ? true : false;

    $data = getDataNews ($this, $reload);
    $return = json_decode('{}');
    if (!empty($data) && isset($data[$args['id']])) {
        $return = $data[$args['id']];
    }

    return $res->withJSON(
        $return,
        200,
        JSON_UNESCAPED_UNICODE
    );
});

$app->get('/', function (Request $req, Response $res, array $args) {
    return $this->renderer->render($res, 'index.phtml', []);
});

/**
 * Возвращяет массив данных «последние новости» с кеширование в файле
 * @param $self - this приложения
 * @param $reload - флаг обновления кеша
 * @return array|mixed
 */
function getDataNews ($self, $reload = false) {
    $DOMEN_NAME_MOBILE = 'https://m.lenta.ru' . DIRECTORY_SEPARATOR;
    $DOMEN_NAME = 'https://lenta.ru' . DIRECTORY_SEPARATOR;
    $FILE_DATA = __DIR__ . '/../public/data.json';

    if (!file_exists($FILE_DATA) || $reload === true) {

        if ($data = getDataLatestNews ($self->dom, $DOMEN_NAME, $DOMEN_NAME_MOBILE)) {
            $someJSON = json_encode($data, JSON_UNESCAPED_SLASHES);
            $fp = fopen($FILE_DATA, 'w');
            fwrite($fp, $someJSON);
            fclose($fp);
        }

    } else {
        $contents = file_get_contents($FILE_DATA, true);
        $data = json_decode($contents, true);
    }

    return $data;
}

/**
 * Возвращяет массив данных «последние новости»
 * @param $dom - dom объект
 * @param $domenName - адрес сайта
 * @param $domenNameMobile - адрес мобильного сайта
 * @return array
 */
function getDataLatestNews ($dom, $domenName, $domenNameMobile) {

    try {
        if (empty($dom) && !is_object($dom))  throw new Exception('DOM объект не указан или не объект');
        if (empty($domenName) && !is_string($domenName))  throw new Exception('Имя сайта не заданно или это не строка');
        if (empty($domenNameMobile) && !is_string($domenNameMobile))  throw new Exception('Имя сайта не заданно или это не строка');

        // массив результата
        $linkNews = [];
        $dom->loadFromUrl($domenNameMobile);
        //$dom->load(stream_get_contents(fopen($domenNameMobile, "rb")));
        $mainNewsContainer = $dom->find('.b-list');

        $i = 0;
        $id = 1;

        if (!empty($mainNewsContainer[0])) {
            while (1) {
                $itemNews = $mainNewsContainer->find('a')[$i];
                if (is_null($itemNews)) {
                    break;
                }

                $href = $itemNews->getAttribute('href') ;
                $url = $domenNameMobile . $href;
                $link = $domenName . $href;

                // Забираем страницу новости
                $dom->loadFromUrl($url);
                //$dom->load(stream_get_contents(fopen($url, "rb")));
                $textDOM = $dom->find('.b-topic__body');

                // удаляем вставку(рекламу)
                /*$deleteAside = $textDOM->find('aside');
                if (!is_null($deleteAside)) {
                    $deleteAside->delete();
                    unset($deleteAside);
                }*/

                //if (false)
                if (!empty($textDOM[0])) {
                    $desc =  mb_strimwidth(strip_tags($textDOM->outerHtml), 0, 150, "...");
                    $linkNews[] = [
                        'id' => $id,
                        'link' => $link,
                        'text' => $textDOM->outerHtml,
                        'desc' => $desc,
                        'datetime' => $dom->find('time')->getAttribute('datetime'),
                        'image' => $dom->find('img')->getAttribute('src'),
                        'title' => $dom->find('h1')->text
                    ];
                }
                $id++;
                $i++;
            }
        }

        return $linkNews;

    } catch (Exception $e) {
        echo 'Выброшено исключение: ',  $e->getMessage(), "\n";
    }
}

/**
 * Возвращяет массив ссылок новостей со страницы
 * @param $dom
 * @return array
 */
function getDataPage ($dom) {
    $linkNews = [];
    $i = 0;

    $domSubElement = $dom;

    $mainNewsDOM = $dom->find('.first-item');
    $mainNewsLinkNewsDOM = $mainNewsDOM->find('.topic-title-pic__link');
    $mainNewsTitleDOM = $mainNewsDOM->find('h2 > a');
    $mainNewsDateDOM = $mainNewsTitleDOM->find('time');


    $link = $mainNewsLinkNewsDOM->getAttribute('href');
    $linkNews[$link]['link'] = $link;
    $linkNews[$link]['desc'] = $mainNewsDOM->find('.announce')->text();
    $linkNews[$link]['datetime'] = $mainNewsDateDOM->getAttribute('datetime');
    $linkNews[$link]['image'] = $mainNewsLinkNewsDOM->find('.g-picture')->getAttribute('src');
    $linkNews[$link]['title'] = $mainNewsTitleDOM->text;

    while (1) {

        $a = $dom->find('a')[$i];
        if (is_null($a)) break;
        $src = '';
        $link = $a->getAttribute('href');
        preg_match('/(\/news\/[0-9a-z\/\_]*)*/i', $link, $matchesNews);
        preg_match('/(\/articles\/[0-9a-z\/\_]*)*/i', $link, $matchesArticle);
        preg_match('/(\/brief\/[0-9a-z\/\_]*)*/i', $link, $matchesbBief);


        if (!empty($matchesNews[0])) {

            $title = $a->text;

            $isSrc = $a->find('img')[0];
            if (!is_null($isSrc)) {
                $src = $isSrc->getAttribute('src');
                $linkNews[$matchesNews[0]]['image'] = $src;
            }

            if ($title !== 'Читать полностью' && !empty($matchesNews[0])) {

                $timeNews = $a->find('time')[0];
                if (!is_null($timeNews)) {
                    $linkNews[$matchesNews[0]]['datetime'] = $timeNews->getAttribute('datetime');
                }

                $linkNews[$matchesNews[0]]['link'] = $matchesNews[0];
                if (!empty($title)) {
                    $linkNews[$matchesNews[0]]['title'] = $title;
                }

                if (!empty($src)) {
                    $linkNews[$matchesNews[0]]['image'] = $src;

                }
                if (empty($linkNews[$matchesNews[0]]['desc']))
                    $linkNews[$matchesNews[0]]['desc'] = '';
            }
        }

        if (!empty($matchesArticle[0])) {
            $title = $a->text;

            if (empty($title)) {
                $domSubElement->load($a->outerHtml);
                $title = $a->find('span')[0]->text;

                $isSrc = $domSubElement->find('img')[0];
                if (!is_null($isSrc)) {
                    $src = $isSrc->getAttribute('src');
                    $linkNews[$matchesArticle[0]]['image'] = $src;
                    $linkNews[$matchesArticle[0]]['desc'] = $a->nextSibling()->find('.rightcol')[0]->text;
                }

            }

            if (!empty($title) && $title !== 'Читать полностью') {
                $linkNews[$matchesArticle[0]]['link'] = $matchesArticle[0];
                if (!empty($title)) {
                    $linkNews[$matchesArticle[0]]['title'] = $title;
                }

                if (!empty($src)) {
                    $linkNews[$matchesArticle[0]]['image'] = $src;
                }
            }
        }

        if (!empty($matchesbBief[0])) {
            $title = $a->text;

            if (empty($title)) {
                $domSubElement->load($a->outerHtml);
                $title = $a->find('span')[0]->text;

                $isSrc = $domSubElement->find('img')[0];
                if (!is_null($isSrc)) {
                    $src = $isSrc->getAttribute('src');
                    $linkNews[$matchesbBief[0]]['image'] = $src;
                    $linkNews[$matchesbBief[0]]['desc'] = $a->nextSibling()->find('.rightcol')[0]->text;
                }

            }

            if (!empty($title) && $title !== 'Читать полностью') {
                $linkNews[$matchesbBief[0]]['link'] = $matchesbBief[0];
                if (!empty($title)) {
                    $linkNews[$matchesbBief[0]]['title'] = $title;
                }

                if (!empty($src)) {
                    $linkNews[$matchesbBief[0]]['image'] = $src;
                }
            }
        }

        $i++;
    }

    return $linkNews;
}