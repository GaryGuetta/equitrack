import { Controller } from '@hotwired/stimulus';

/*
 * L'en-tête prend une ombre dès qu'on fait défiler la page.
 */
export default class extends Controller {
    connect() {
        this.onScroll = () => this.element.classList.toggle('is-scrolled', window.scrollY > 8);
        window.addEventListener('scroll', this.onScroll, { passive: true });
        this.onScroll();
    }

    disconnect() {
        window.removeEventListener('scroll', this.onScroll);
    }
}
