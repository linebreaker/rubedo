<?php
/**
 * Rubedo -- ECM solution
 * Copyright (c) 2014, WebTales (http://www.webtales.fr/).
 * All rights reserved.
 * licensing@webtales.fr
 *
 * Open Source License
 * ------------------------------------------------------------------------------------------
 * Rubedo is licensed under the terms of the Open Source GPL 3.0 license.
 *
 * @category   Rubedo
 * @package    Rubedo
 * @copyright  Copyright (c) 2012-2013 WebTales (http://www.webtales.fr)
 * @license    http://www.gnu.org/licenses/gpl.html Open Source GPL 3.0 license
 */

namespace RubedoAPI\Rest\V1;

use RubedoAPI\Exceptions\APIEntityException;
use RubedoAPI\Exceptions\APIRequestException;
use RubedoAPI\Entities\API\Definition\FilterDefinitionEntity;
use RubedoAPI\Entities\API\Definition\VerbDefinitionEntity;

/**
 * Class AbstractRessource
 * @package RubedoAPI\Rest\V1
 */
class ContentsRessource extends AbstractRessource
{

    /**
     * { @inheritdoc }
     */
    public function __construct()
    {
        parent::__construct();
        $this->define();
    }

    /**
     * Get to contents
     *
     * @param $params
     * @return array
     * @throws \RubedoAPI\Exceptions\APIEntityException
     */
    public function getAction($params)
    {

        $queryId = & $params['queryId'];

        $filters = $this->getQueriesCollection()->getFilterArrayById($queryId);

        if ($filters === false) {
            throw new APIEntityException('Query not found', 404);
        }

        if ($filters !== false) {
            $queryType = $filters["queryType"];
            $query = $this->getQueriesCollection()->getQueryById($queryId);

            if ($queryType === "manual" && $query != false && isset($query['query']) && is_array($query['query'])) {
                $contentOrder = $query['query'];
                $keyOrder = array();
                $contentArray = array();

                // getList
                $unorderedContentArray = $this->getContentList($filters, $this->setPaginationValues($params));

                foreach ($contentOrder as $value) {
                    foreach ($unorderedContentArray['data'] as $subKey => $subValue) {
                        if ($value === $subValue['id']) {
                            $keyOrder[] = $subKey;
                        }
                    }
                }

                foreach ($keyOrder as $value) {
                    $contentArray["data"][] = $unorderedContentArray["data"][$value];
                }

                $nbItems = $unorderedContentArray["count"];
            } else {
                $ismagic = isset($params['isMagic']) ? $params['isMagic'] : false;
                $contentArray = $this->getContentList($filters, $this->setPaginationValues($params), $ismagic);
                $nbItems = $contentArray["count"];
            }
        } else {
            $nbItems = 0;
        }
        return [
            'success' => true,
            'contents' => $this->outputContentsMask($contentArray['data'], $params),
            'count' => $nbItems
        ];
    }

    /**
     * Filter contents
     *
     * @param $contents
     * @param $params
     * @return mixed
     */
    protected function outputContentsMask($contents, $params)
    {
        $fields = isset($params['fields']) ? $params['fields'] : array('text', 'summary', 'image');
        $urlService = $this->getUrlAPIService();
        $page = $this->getPagesCollection()->findById($params['pageId']);
        $site = $this->getSitesCollection()->findById($params['siteId']);
        $mask = array('isProduct', 'productProperties', 'i18n', 'pageId', 'blockId', 'maskId');
        foreach ($contents as &$content) {
            $content['fields'] = array_intersect_key($content['fields'], array_flip($fields));
            $content['detailPageUrl'] = $urlService->displayUrlApi($content, 'default', $site,
                $page, $params['lang']->getLocale(), isset($params['detailPageId']) ? $params['detailPageId'] : null);
            $content = array_diff_key($content, array_flip($mask));
        }
        return $contents;
    }

    /**
     * Get content list
     *
     * @param $filters
     * @param $pageData
     * @param bool $ismagic
     * @return array
     * @throws \RubedoAPI\Exceptions\APIEntityException
     */
    protected function getContentList($filters, $pageData, $ismagic = false)
    {
        $filters["sort"] = isset($filters["sort"]) ? $filters["sort"] : array();
        $contentArray = $this->getContentsCollection()->getOnlineList($filters["filter"], $filters["sort"], $pageData['start'], $pageData['limit'], $ismagic);
        $contentArray['page'] = $pageData;
        if ($contentArray['count'] < $pageData['start']) {
            throw new APIEntityException('There is only ' . $contentArray['count'] . ' contents. Start parameter must be inferior of this value', 404);
        }
        return $contentArray;
    }

    /**
     * Set pagination value
     *
     * @param $params
     * @return mixed
     * @throws \RubedoAPI\Exceptions\APIEntityException
     */
    protected function setPaginationValues($params)
    {
        $defaultLimit = isset($params['limit']) ? $params['limit'] : 6;
        $defaultStart = isset($params['start']) ? $params['start'] : 0;
        if ($defaultStart < 0) {
            throw new APIEntityException('Start paramater must be >= 0', 404);
        }
        if ($defaultLimit < 1) {
            throw new APIEntityException('Limit paramater must be >= 1', 404);
        }
        $pageData['start'] = $defaultStart;
        $pageData['limit'] = $defaultLimit;
        return $pageData;
    }

    /**
     * Get to contents/{id}
     *
     * @param $id
     * @param $params
     * @return array
     * @throws \RubedoAPI\Exceptions\APIEntityException
     * @throws \RubedoAPI\Exceptions\APIRequestException
     */
    public function getEntityAction($id, $params)
    {
        $content = $this->getContentsCollection()->findById($id, true, false);
        if (empty($content))
            throw new APIEntityException('Content not found', 404);

        $contentType = $this->getContentTypesCollection()->findById($content['typeId'], true, false);
        if (empty($contentType))
            throw new APIEntityException('ContentType not found', 404);

        $content = array_intersect_key(
            $content,
            array_flip(
                array(
                    'id',
                    'text',
                    'version',
                    'createUser',
                    'lastUpdateUser',
                    'fields',
                    'taxonomy',
                    'status',
                    'pageId',
                    'maskId',
                    'locale',
                    'readOnly',
                )
            )
        );

        if (isset($params['fields'])) {
            if (!is_array($params['fields']))
                throw new APIRequestException('"fields" must be an array', 400);
            $content['fields'] = array_intersect_key($content['fields'], array_flip($params['fields']));
        }

        $content['type'] = array_intersect_key(
            $contentType,
            array_flip(
                array(
                    'id',
                    'code',
                    'activateDisqus',
                    'fields',
                    'locale',
                    'version',
                    'workflow',
                    'readOnly',
                )
            )
        );

        return [
            'success' => true,
            'content' => $content,
        ];
    }

    /**
     * Define the ressource
     */
    protected function define()
    {
        $this
            ->definition
            ->setName('Contents')
            ->setDescription('Deal with contents')
            ->editVerb('get', function (VerbDefinitionEntity &$definition) {
                $this->defineGet($definition);
            });
        $this
            ->entityDefinition
            ->setName('Content')
            ->setDescription('Works on single content')
            ->editVerb('get', function (VerbDefinitionEntity &$definition) {
                $this->defineEntityGet($definition);
            });
    }

    /**
     * Define get action
     *
     * @param VerbDefinitionEntity $definition
     */
    protected function defineGet(VerbDefinitionEntity &$definition)
    {
        $definition
            ->setDescription('Get a list of contents')
            ->addInputFilter(
                (new FilterDefinitionEntity())
                    ->setKey('queryId')
                    ->setRequired()
                    ->setDescription('Id of the query')
                    ->setFilter('\\MongoId')
            )
            ->addInputFilter(
                (new FilterDefinitionEntity())
                    ->setKey('siteId')
                    ->setRequired()
                    ->setDescription('Id of the site')
                    ->setFilter('\\MongoId')
            )
            ->addInputFilter(
                (new FilterDefinitionEntity())
                    ->setKey('pageId')
                    ->setRequired()
                    ->setDescription('Id of the page')
                    ->setFilter('\\MongoId')
            )
            ->addInputFilter(
                (new FilterDefinitionEntity())
                    ->setKey('detailPageId')
                    ->setDescription('Id of the linked page')
                    ->setFilter('\\MongoId')
            )
            ->addInputFilter(
                (new FilterDefinitionEntity())
                    ->setKey('fields')
                    ->setDescription('Mask of fields')
            )
            ->addInputFilter(
                (new FilterDefinitionEntity())
                    ->setKey('dateFieldName')
                    ->setDescription('Name of the date field')
            )
            ->addInputFilter(
                (new FilterDefinitionEntity())
                    ->setKey('isMagic')
                    ->setDescription('Property is Magic query')
                    ->setFilter('boolean')
            )
            ->addInputFilter(
                (new FilterDefinitionEntity())
                    ->setKey('start')
                    ->setDescription('Item\'s index number to start')
                    ->setFilter('int')
            )
            ->addInputFilter(
                (new FilterDefinitionEntity())
                    ->setKey('limit')
                    ->setDescription('How much contents to return')
                    ->setFilter('int')
            )
            ->addOutputFilter(
                (new FilterDefinitionEntity())
                    ->setKey('contents')
                    ->setDescription('List of contents')
            )
            ->addOutputFilter(
                (new FilterDefinitionEntity())
                    ->setKey('count')
                    ->setDescription('Number of all contents')
            );
    }

    /**
     * Define get entity
     *
     * @param VerbDefinitionEntity $definition
     */
    protected function defineEntityGet(VerbDefinitionEntity &$definition)
    {
        $definition
            ->setDescription('Get a content')
            ->addInputFilter(
                (new FilterDefinitionEntity())
                    ->setDescription('Fields to return')
                    ->setKey('fields')
            )
            ->addOutputFilter(
                (new FilterDefinitionEntity())
                    ->setDescription('The content')
                    ->setKey('content')
                    ->setRequired()
            );
    }
}