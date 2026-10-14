<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-gradient-to-r from-marca-800 to-marca-900 border border-transparent rounded-lg font-semibold text-xs text-oro-100 uppercase tracking-widest shadow-sm hover:from-marca-700 hover:to-marca-800 focus:outline-none focus:ring-2 focus:ring-oro-400 focus:ring-offset-2 active:scale-[0.99] disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
