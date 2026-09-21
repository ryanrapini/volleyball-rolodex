/*
 * Laravel's CSRF cookie, for the handful of places that talk to the server with a
 * plain fetch instead of an Inertia visit.
 */
export const xsrfToken = () => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};
