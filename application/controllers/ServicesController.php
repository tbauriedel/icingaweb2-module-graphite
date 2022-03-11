<?php

namespace Icinga\Module\Graphite\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Graphite\Util\TimeRangePickerTools;
use Icinga\Module\Graphite\Web\Controller\IcingadbGraphiteController;
use Icinga\Module\Graphite\Web\Controller\TimeRangePickerTrait;
use Icinga\Module\Graphite\Web\Widget\IcingadbGraphs;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Icingadb\Common\SearchControls;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;
use ipl\Html\HtmlString;
use ipl\Orm\Query;
use ipl\Stdlib\Filter;
use ipl\Web\Control\LimitControl;
use ipl\Web\Control\SortControl;
use ipl\Web\Filter\QueryString;
use ipl\Web\Url;

class ServicesController extends IcingadbGraphiteController
{
    use TimeRangePickerTrait;

    public function indexAction()
    {
        $graphRange = $this->params->shift('graph_range');
        $baseFilter = $graphRange ? QueryString::parse('graph_range=' . $graphRange) : $this->getFilter();
        // shift graph params to avoid exception
        foreach (['graphs_limit', 'graph_start', 'graph_end'] as $param) {
            $this->params->shift($param);
        }

        $this->setTitle(t('Services'));

        $db = $this->getDb();

        $services = Service::on($db)->with(['state', 'host']);
        $services->filter(Filter::equal('state.performance_data', '*'));

        $this->applyRestrictions($services);

        $limitControl = $this->createLimitControl();
        $paginationControl = $this->createPaginationControl($services);
        $sortControl = $this->createSortControl($services, [
            'service.display_name' => t('Servicename'),
            'host.display_name' => t('Hostname')
        ]);

        $searchBar = $this->createSearchBar($services, [
            $limitControl->getLimitParam(),
            $sortControl->getSortParam()
        ]);

        if ($searchBar->hasBeenSent() && ! $searchBar->isValid()) {
            if ($searchBar->hasBeenSubmitted()) {
                $filter = $this->getFilter();
            } else {
                $this->addControl($searchBar);
                $this->sendMultipartUpdate();
                return;
            }
        } else {
            $filter = $searchBar->getFilter();
        }

        $this->filter($services, $filter);

        $this->addControl($paginationControl);
        $this->addControl($sortControl);
        $this->addControl($limitControl);
        $this->addControl($searchBar);
        $this->handleTimeRangePickerRequest();
        $this->addControl(HtmlString::create($this->renderTimeRangePicker($this->view)));

        $this->addContent((new IcingadbGraphs($services->execute()))->setBaseFilter($baseFilter));

        if (! $searchBar->hasBeenSubmitted() && $searchBar->hasBeenSent()) {
            $this->sendMultipartUpdate();
        }
    }

    public function completeAction()
    {
        $suggestions = new ObjectSuggestions();
        $suggestions->setModel(Service::class);
        $suggestions->forRequest(ServerRequest::fromGlobals());
        $this->getDocument()->add($suggestions);
    }

    public function searchEditorAction()
    {
        $editor = $this->createSearchEditor(
            Service::on($this->getDb()),
            [LimitControl::DEFAULT_LIMIT_PARAM, SortControl::DEFAULT_SORT_PARAM]
        );

        $this->getDocument()->add($editor);
        $this->setTitle(t('Adjust Filter'));
    }
}
