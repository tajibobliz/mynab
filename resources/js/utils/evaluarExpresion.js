/**
 * Evaluador de expresiones aritméticas simples (+, -, *, /, paréntesis,
 * decimales) sin usar eval()/Function() del navegador ni ninguna librería
 * externa. Reemplaza a expr-eval (Change 7 Grupo 8): expr-eval@2.0.2 tiene
 * 3 CVEs críticos sin parche (prototype pollution + code execution,
 * GHSA-8gw3-rxh4-v6jx / GHSA-jc85-fpwf-qm7x / GHSA-q9v2-7m5w-4693),
 * decisión tomada con el usuario de construir un parser propio en vez de
 * aceptar ese riesgo.
 *
 * Shunting-yard clásico: tokeniza -> notación postfija -> evalúa postfija.
 * Soporta unario +/- (ej. "-5+2", "3*-5") con precedencia propia, distinto
 * de la resta/suma binaria.
 */

const OPERADORES_BINARIOS = {
    '+': { prec: 1, fn: (a, b) => a + b },
    '-': { prec: 1, fn: (a, b) => a - b },
    '*': { prec: 2, fn: (a, b) => a * b },
    '/': { prec: 2, fn: (a, b) => a / b },
};

const UNARIO_PREC = 3;

function tokenizar(expr) {
    const tokens = [];
    let i = 0;

    while (i < expr.length) {
        const c = expr[i];

        if (c === ' ') {
            i++;
            continue;
        }

        if ('+-*/()'.includes(c)) {
            tokens.push({ tipo: 'op', valor: c });
            i++;
            continue;
        }

        if (/[0-9.]/.test(c)) {
            let num = '';
            while (i < expr.length && /[0-9.]/.test(expr[i])) {
                num += expr[i];
                i++;
            }
            if ((num.match(/\./g) ?? []).length > 1) {
                throw new Error('Número con más de un punto decimal');
            }
            tokens.push({ tipo: 'num', valor: parseFloat(num) });
            continue;
        }

        throw new Error(`Carácter no permitido: "${c}"`);
    }

    return tokens;
}

/**
 * Marca como unarios los '+'/'-' que aparecen al inicio, tras otro
 * operador, o tras '(' — en vez de insertar un "0 -" implícito (ese truco
 * rompe la precedencia cuando el unario sigue a un operador de mayor
 * precedencia ya en la pila, ej. "3*-5" se evaluaba mal como -5 en vez de
 * -15 en el primer intento de este archivo).
 */
function marcarUnarios(tokens) {
    return tokens.map((token, i) => {
        if (token.tipo !== 'op' || (token.valor !== '+' && token.valor !== '-')) {
            return token;
        }

        const anterior = tokens[i - 1];
        const esUnario = anterior === undefined || (anterior.tipo === 'op' && anterior.valor !== ')');

        return esUnario ? { tipo: 'op', valor: token.valor === '-' ? 'u-' : 'u+', unario: true } : token;
    });
}

function aNotacionPostfija(tokens) {
    const salida = [];
    const pila = [];

    const precedencia = (valor) => (valor === 'u-' || valor === 'u+' ? UNARIO_PREC : OPERADORES_BINARIOS[valor].prec);

    for (const token of tokens) {
        if (token.tipo === 'num') {
            salida.push(token);
        } else if (token.valor === '(') {
            pila.push(token);
        } else if (token.valor === ')') {
            while (pila.length && pila[pila.length - 1].valor !== '(') {
                salida.push(pila.pop());
            }
            if (!pila.length) {
                throw new Error('Paréntesis desbalanceados');
            }
            pila.pop();
        } else {
            // Unarios son right-associative: solo desapilar si el tope tiene
            // precedencia ESTRICTAMENTE mayor, no igual (si no, "--5" o
            // "-(-5)" encadenados se asociarían mal).
            const esUnarioActual = token.valor === 'u-' || token.valor === 'u+';

            while (
                pila.length
                && pila[pila.length - 1].valor !== '('
                && (esUnarioActual
                    ? precedencia(pila[pila.length - 1].valor) > precedencia(token.valor)
                    : precedencia(pila[pila.length - 1].valor) >= precedencia(token.valor))
            ) {
                salida.push(pila.pop());
            }
            pila.push(token);
        }
    }

    while (pila.length) {
        const op = pila.pop();
        if (op.valor === '(') {
            throw new Error('Paréntesis desbalanceados');
        }
        salida.push(op);
    }

    return salida;
}

function evaluarPostfija(postfija) {
    const pila = [];

    for (const token of postfija) {
        if (token.tipo === 'num') {
            pila.push(token.valor);
            continue;
        }

        if (token.valor === 'u-' || token.valor === 'u+') {
            const a = pila.pop();
            if (a === undefined) {
                throw new Error('Expresión incompleta');
            }
            pila.push(token.valor === 'u-' ? -a : a);
            continue;
        }

        const b = pila.pop();
        const a = pila.pop();
        if (a === undefined || b === undefined) {
            throw new Error('Expresión incompleta');
        }
        pila.push(OPERADORES_BINARIOS[token.valor].fn(a, b));
    }

    if (pila.length !== 1) {
        throw new Error('Expresión incompleta');
    }

    return pila[0];
}

/**
 * @param {string} expresionCruda Texto tal como lo escribió el usuario.
 * @returns {{ ok: true, valor: number } | { ok: false, vacio?: true }}
 */
export function evaluarExpresion(expresionCruda) {
    const expr = (expresionCruda ?? '').trim().replace(/,/g, '.');

    if (expr === '') {
        return { ok: false, vacio: true };
    }

    try {
        const tokens = tokenizar(expr);
        if (tokens.length === 0) {
            throw new Error('Expresión vacía');
        }
        const conUnarios = marcarUnarios(tokens);
        const postfija = aNotacionPostfija(conUnarios);
        const valor = evaluarPostfija(postfija);

        if (!Number.isFinite(valor)) {
            throw new Error('Resultado inválido');
        }

        return { ok: true, valor };
    } catch {
        return { ok: false };
    }
}
