<div class="flex items-center justify-end gap-1">
    <x-ui.icon-button :href="route('categories.show', $category)" icon="eye" :label="__('View')" />

    @can('categories.edit')
        <x-ui.icon-button :href="route('categories.edit', $category)" icon="pencil" :label="__('Edit')" />
        <form method="POST" action="{{ route('categories.toggle', $category) }}">
            @csrf
            @method('PATCH')
            <x-ui.icon-button type="submit" icon="power" :label="$category->is_active ? __('Deactivate') : __('Activate')" />
        </form>
    @endcan

    @can('categories.delete')
        <x-ui.icon-button
            icon="trash"
            variant="danger"
            :label="__('Delete')"
            :confirm="[
                'action' => route('categories.destroy', $category),
                'title' => __('Delete :name?', ['name' => $category->name]),
                'message' => __('Categories that still have products cannot be deleted. This cannot be undone.'),
            ]"
        />
    @endcan
</div>
