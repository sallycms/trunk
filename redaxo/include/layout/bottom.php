
			</div>
		</div>
	</div>

	<div id="rex-footer">
		<div id="rex-navi-footer">
			<ul>
				<li class="rex-navi-first"><a href="#rex-header">&#94;</a></li>
				<li><a href="http://www.webvariants.de/" class="sly-blank">webvariants.de</a></li>
				<li><a href="http://www.sallycms.de/" class="sly-blank">sallycms.de</a></li>
				<? if (isset($REX['USER'])): ?>
				<li><a href="index.php?page=credits"><?= $I18N->msg('credits') ?></a></li>
				<? endif ?>
			</ul>
			<p id="rex-scripttime"><!--DYN--><?= rex_showScriptTime() ?> Sek | <?= rex_formatter::format(time(), 'strftime', 'date'); ?><!--/DYN--></p>
		</div>
	</div>

	<div id="rex-extra"></div>
	<div id="rex-redaxo-link"><p><a href="./">Wohin verlinke ich?</a></p></div>

</div>
</body>
</html>