#!/usr/bin/env python3
"""Generates boilerplate Filament page classes for each resource.
Usage: scaffold_pages.py <app_dir> <Name> <PluralDir> <pages,...> [--drafts=permission]"""
import os, sys

app, name, plural, pages = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4].split(',')
drafts = next((a.split('=', 1)[1] for a in sys.argv[5:] if a.startswith('--drafts=')), None)
ns = f"App\\Filament\\Resources\\{plural}"
res = f"{name}Resource"
d = os.path.join(app, 'Filament', 'Resources', plural, 'Pages')
os.makedirs(d, exist_ok=True)
plural_cls = plural

templates = {
'list': f'''<?php

namespace {ns}\\Pages;

use {ns}\\{res};
use Filament\\Actions\\CreateAction;
use Filament\\Resources\\Pages\\ListRecords;

class List{plural_cls} extends ListRecords
{{
    protected static string $resource = {res}::class;

    protected function getHeaderActions(): array
    {{
        return {res}::canCreate() ? [CreateAction::make()] : [];
    }}
}}
''',
'create': f'''<?php

namespace {ns}\\Pages;

use {ns}\\{res};
use Filament\\Resources\\Pages\\CreateRecord;

class Create{name} extends CreateRecord
{{
    protected static string $resource = {res}::class;

    protected function getRedirectUrl(): string
    {{
        return static::getResource()::hasPage('view') ? static::getResource()::getUrl('view', ['record' => $this->getRecord()]) : static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }}
}}
''',
'view': f'''<?php

namespace {ns}\\Pages;

use {ns}\\{res};
use Filament\\Actions\\EditAction;
use Filament\\Resources\\Pages\\ViewRecord;

class View{name} extends ViewRecord
{{
    protected static string $resource = {res}::class;

    protected function getHeaderActions(): array
    {{
        return method_exists({res}::class, 'viewHeaderActions') ? {res}::viewHeaderActions($this) : ({res}::hasPage('edit') ? [EditAction::make()] : []);
    }}
}}
''',
}
if drafts:
    templates['edit'] = f'''<?php

namespace {ns}\\Pages;

use App\\Filament\\Concerns\\InteractsWithDrafts;
use {ns}\\{res};
use Filament\\Resources\\Pages\\EditRecord;

class Edit{name} extends EditRecord
{{
    use InteractsWithDrafts;

    protected static string $resource = {res}::class;

    protected static string $publishPermission = '{drafts}';

    protected function getHeaderActions(): array
    {{
        return $this->draftActions();
    }}

    protected function getSaveFormAction(): \\Filament\\Actions\\Action
    {{
        return parent::getSaveFormAction()->label(fn () => $this->getRecord()->is_published ? 'Save draft' : 'Save');
    }}
}}
'''
else:
    templates['edit'] = f'''<?php

namespace {ns}\\Pages;

use {ns}\\{res};
use Filament\\Actions\\DeleteAction;
use Filament\\Resources\\Pages\\EditRecord;

class Edit{name} extends EditRecord
{{
    protected static string $resource = {res}::class;

    protected function getHeaderActions(): array
    {{
        return method_exists({res}::class, 'editHeaderActions') ? {res}::editHeaderActions($this) : [DeleteAction::make()];
    }}
}}
'''

files = {'list': f'List{plural_cls}.php', 'create': f'Create{name}.php', 'edit': f'Edit{name}.php', 'view': f'View{name}.php'}
for p in pages:
    path = os.path.join(d, files[p])
    if os.path.exists(path) and '--force' not in sys.argv:
        continue
    open(path, 'w').write(templates[p])
print(f"{plural}: {', '.join(pages)}")
