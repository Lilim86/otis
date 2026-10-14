@props(['disabled' => false])
<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-gray-300 focus:border-oro-500 focus:ring-oro-400 rounded-md shadow-sm']) !!}>
