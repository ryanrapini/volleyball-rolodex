/*
 * Tag colours are chosen per answer, so the text colour has to be worked out
 * rather than taken from the theme: pale yellow needs dark ink, royal blue needs
 * white.
 */

export const readableInk = (colour) => {
    if (typeof colour !== 'string') {
        return undefined;
    }

    const trimmed = colour.replace('#', '').toLowerCase();

    const hex =
        trimmed.length === 3
            ? trimmed
                  .split('')
                  .map((part) => part + part)
                  .join('')
            : trimmed;

    if (!/^[0-9a-f]{6}$/.test(hex)) {
        return undefined;
    }

    const [red, green, blue] = [0, 2, 4].map((at) => parseInt(hex.slice(at, at + 2), 16));
    const luminance = (0.299 * red + 0.587 * green + 0.114 * blue) / 255;

    return luminance > 0.6 ? '#111827' : '#ffffff';
};

/** The inline style for a coloured tag, or nothing when it has no colour. */
export const tagStyle = (colour) =>
    colour
        ? { background: colour, borderColor: colour, color: readableInk(colour) }
        : undefined;
