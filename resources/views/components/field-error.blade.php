@props(['name', 'id' => null])
@error($name)
    <p id="{{ $id ?? str_replace('.', '-', $name) }}-error" class="form-error">
        <x-icon name="circle-alert" class="size-4" />{{ $message }}
    </p>
@enderror
