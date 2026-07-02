// Recipes index — favorite toggle + send-to-list modal
window.recipesIndex = function () {
    return {
        toast: null,
        async toggleFavorite(id, el) {
            try {
                const res = await fetch(`/recipes/${id}/favorite`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                });
                if (!res.ok) return;
                const data = await res.json();
                el.dataset.fav = data.is_favorite ? '1' : '0';
            } catch (e) { /* ignore */ }
        },
    };
};
