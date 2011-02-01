<?php
/**
 *
 */
class ExternalContentAdmin extends ModelAdmin {

	public static $title       = 'External Content';
	public static $menu_title  = 'External Content';
	public static $url_segment = 'external-content';

	public static $managed_models = array(
		'ExternalContentSource' => array(
			'record_controller' => 'ExternalContentAdmin_SourceController'
		),
		'ExternalContentItem' => array(
			'collection_controller' => 'ExternalContentAdmin_ItemCollectionController',
			'record_controller'     => 'ExternalContentAdmin_ItemController'
		)
	);

	public static $allowed_actions = array(
		'tree',
		'CreateSourceForm'
	);

	/**
	 * Returns a set of nodes to insert into the external content tree.
	 */
	public function tree($request) {
		$result = array();
		$nodes  = array();
		$id     = $request->getVar('id');

		if (!$id) {
			$nodes = DataObject::get('ExternalContentSource');
			$class = 'ExternalContentSource';
		} else {
			$class  = 'ExternalContentItem';

			if (ctype_digit($id)) {
				$parent = DataObject::get_by_id('ExternalContentSource', $id);
			} else {
				$parent = ExternalContent::getDataObjectFor($id);
			}

			if ($parent) $nodes = $parent->Children();
		}

		if ($nodes) foreach ($nodes as $node) {
			$push = array(
				'data' => array(
					'title' => $node->TreeTitle(),
					'attr'  => array('href' => $this->Link("$class/{$node->ID}/edit")),
					'icon'  => $node->Icon()),
				'metadata' => array(
					'id'    => $node->ID,
					'type'  => $class)
			);

			if ($node->numChildren()) {
				$push['state'] = 'closed';
			}

			$result[] = $push;
		}

		$response = new SS_HTTPResponse();
		$response->addHeader('Content-Type', 'application/json');
		$response->setBody(Convert::array2json($result));
		return $response;
	}

	/**
	 * Returns the form used to create a new content source.
	 *
	 * @return Form
	 */
	public function CreateSourceForm() {
		$classes = ClassInfo::subclassesFor('ExternalContentSource');
		$sources = array();

		array_shift($classes);
		foreach ($classes as $class) {
			if (singleton($class)->canCreate()) {
				$sources[$class] = singleton($class)->singular_name();
			}
		}

		$fields = new FieldSet(
			new DropdownField('ClassName', '', $sources, null, null, _t(
				'ExternalContent.SELECT', '(Select)'
			))
		);

		return new Form($this, 'CreateSourceForm', $fields, new FieldSet(
			new FormAction('doCreateSource', _t('ExternalContent.GO', 'Go'))
		));
	}

	public function doCreateSource($data, $form) {
		$class = $data['ClassName'];

		if (!is_subclass_of($class, 'ExternalContentSource')) {
			return new SS_HTTPResponse(null, 400, _t(
				'ExternalContent.INVALIDCLASS',
				'An invalid source class was specified.'));
		}

		$source = new $class();
		$source->Name = sprintf(
			_t('ExternalContent.NEWITEM', 'New %s'), $source->singular_name()
		);
		$source->write();

		$control = $this->getRecordControllerClass('ExternalContentSource');
		$control = new $control($this->ExternalContentSource(), null, $source->ID);
		$form    = $control->EditForm();

		return new SS_HTTPResponse($form->forAjaxTemplate(), 200, _t(
			'ExternalContent.SOURCECREATED', 'The external content source has been created.'
		));
	}

}

class ExternalContentAdmin_RecordController extends ModelAdmin_RecordController {

	/**
	 * @return Form
	 */
	public function EditForm() {
		$form   = parent::EditForm();
		$record = $this->currentRecord;
		$fields = $form->Fields();

		$form->Actions()->removeByName('action_goForward');
		$form->Actions()->removeByName('action_goBack');

		// If we're editing an external source or item, and it can be imported
		// then add the "Import" tab.
		$isSource = $record instanceof ExternalContentSource;
		$isItem   = $record instanceof ExternalContentItem;

		if (($isSource || $isItem) && $record->canImport()) {
			$targets = $record->allowedImportTargets();

			if (isset($targets['sitetree'])) {
				$fields->addFieldToTab('Root.Import', new TreeDropdownField(
					'MigrationTarget',
					_t('ExternalContent.MIGRATE_TARGET', 'Page to import into'),
					'SiteTree'
				));
			}

			if (isset($allowedTypes['file'])) {
				$fields->addFieldToTab('Root.Import', new TreeDropdownField(
					'FileMigrationTarget',
					_t('ExternalContent.FILE_MIGRATE_TARGET', 'Folder to import into'),
					'Folder'
				));
			}

			$fields->addFieldsToTab('Root.Import', array(
				new CheckboxField('IncludeSelected',
					_t('ExternalContent.INCLUDE_SELECTED', 'Include Selected Item in Import')),
				new CheckboxField('IncludeChildren',
					_t('ExternalContent.INCLUDE_CHILDREN', 'Include Child Items in Import'), true)
			));

			$strategies = array(
				ExternalContentTransformer::DS_OVERWRITE => ExternalContentTransformer::DS_OVERWRITE,
				ExternalContentTransformer::DS_DUPLICATE => ExternalContentTransformer::DS_DUPLICATE,
				ExternalContentTransformer::DS_SKIP      => ExternalContentTransformer::DS_SKIP,
			);

			$fields->addFieldToTab('Root.Import', new OptionsetField(
				'DuplicateMethod',
				_t('ExternalContent.DUPLICATES', 'Select how duplicate items should be handled'),
				$strategies
			));

			$fields->addFieldToTab('Root.Import', $action = new FormAction_WithoutLabel(
				'doImport'
			));

			$action->setButtonContent(_t('ExternalContent.IMPORT', 'Start Importing'));
			$action->useButtonTag = true;
		}

		return $form;
	}

}

class ExternalContentAdmin_SourceController extends ExternalContentAdmin_RecordController {

	/**
	 * @return string
	 */
	public function doDelete() {
		if($this->currentRecord->canDelete()) {
			$this->currentRecord->delete();

			$form = new Form($this, 'EditForm', new FieldSet(new LiteralField(
				'RecordDeleted',
				'<p>' . _t('ExternalContent.CONNDELETED', 'This connector has been deleted.') . '</p>'
			)), new FieldSet());

			return $form->forAjaxTemplate();
		} else {
			return $this->redirectBack();
		}
	}

}

class ExternalContentAdmin_ItemCollectionController extends ModelAdmin_CollectionController {

	public function handleActionOrID($request) {
		return $this->handleID($request);
	}

}

class ExternalContentAdmin_ItemController extends ExternalContentAdmin_RecordController {

	public function __construct($parent, $request, $recordID = null) {
		$this->parentController = $parent;
		$this->currentRecord = ExternalContent::getDataObjectFor(
			$recordID ? $recordID : $request->param('Action')
		);

		Controller::__construct();
	}

	public function EditForm() {
		$form = parent::EditForm();
		$form->setActions(new FieldSet());
		return $form;
	}

}
