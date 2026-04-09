/**
 * Vértice Acadêmico — Sistema de Geração da Ata do Conselho
 */

async function loadCouncilAta(conselhoId) {
    const container = document.getElementById('ata_content_area');
    if (!container) return;

    container.innerHTML = '<div style="text-align:center; padding:3rem; color:var(--text-muted);">⏳ Consolidando todas as informações da sessão...</div>';

    try {
        const resp = await fetch(`/courses/conselho_ata_ajax.php?conselho_id=${conselhoId}`);
        const result = await resp.json();

        if (result.success) {
            const { info, presentes, ausentes, registros, encaminhamentos } = result.data;
            
            const dataObj = new Date(info.data_hora);
            const dataStr = dataObj.toLocaleDateString('pt-BR');
            const horaStr = dataObj.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
            
            const listaPresentes = presentes.map(p => `<b>${p.name}</b> (${p.profile})`).join(', ');
            const listaAusentes = (ausentes && ausentes.length > 0) ? ausentes.map(p => `<b>${p.name}</b> (${p.profile})`).join(', ') : null;

            let html = `
                <div class="ata-document" style="background:#fff; border:1px solid #ddd; padding:4rem; box-shadow:0 5px 15px rgba(0,0,0,0.05); color:#333; font-family:'Times New Roman', serif; line-height:1.8; text-align:justify;">
                    
                    <!-- Cabeçalho Institucional -->
                    <div style="display:flex; align-items:flex-start; gap:1.5rem; border-bottom:2px solid #333; padding-bottom:1rem; margin-bottom:2.5rem; text-align:left;">
                        ${info.institution_logo ? `
                            <div style="width:80px; height:80px; flex-shrink:0;">
                                <img src="/${info.institution_logo}" style="width:100%; height:100%; object-fit:contain;">
                            </div>
                        ` : ''}
                        <div style="flex:1;">
                            <h1 style="margin:0; font-size:1.25rem; text-transform:uppercase; font-family:sans-serif; font-weight:800; line-height:1.2;">${info.institution_name}</h1>
                            <div style="font-size:0.8125rem; font-weight:700; color:#000; margin-top:4px;">CNPJ: ${info.institution_cnpj}</div>
                            <div style="font-size:0.75rem; color:#444; margin-top:2px; line-height:1.4;">${info.institution_address}</div>
                        </div>
                    </div>

                    <div style="text-align:center; margin-bottom:3rem;">
                        <h2 style="margin:0; text-transform:uppercase; letter-spacing:3px; font-size:1.6rem; font-family:sans-serif; font-weight:800;">ATA DE CONSELHO DE CLASSE</h2>
                        <div style="font-size:1.1rem; margin-top:0.4rem; font-weight:700; color:#333;">${info.course_name} — ${info.turma_name}</div>
                        ${info.descricao ? `<div style="font-size:0.95rem; margin-top:0.3rem; font-weight:600; color:#555;">${info.descricao}</div>` : ''}
                    </div>

                    <!-- Seção 1: Participantes (Narrativa) -->
                    <section style="margin-bottom:2.5rem;">
                        <h3 style="font-size:1.1rem; border-bottom:1px solid #333; padding-bottom:5px; margin-bottom:1.25rem; color:#000; text-transform:uppercase; font-weight:800;">1. ABERTURA E PARTICIPANTES</h3>
                        <p style="text-indent:2rem; margin:0;">
                            Estiveram presentes no conselho de classe <b>${info.descricao}</b> do dia <b>${dataStr}</b>, referente à turma <b>${info.turma_name}</b>, com início às <b>${horaStr}</b> horas, os(as) seguintes servidores(as): ${listaPresentes}.
                        </p>
                        ${listaAusentes ? `
                        <p style="text-indent:2rem; margin:1rem 0 0 0;">
                            Constatada a ausência dos(as) servidores(as): ${listaAusentes}.
                        </p>
                        ` : ''}
                    </section>

                    <!-- Seção 2: Registros e Discussões -->
                    <section style="margin-bottom:2.5rem;">
                        <h3 style="font-size:1.1rem; border-bottom:1px solid #333; padding-bottom:5px; margin-bottom:1.25rem; color:#000; text-transform:uppercase; font-weight:800;">2. REGISTROS PEDAGÓGICOS E DISCUSSÕES</h3>
                        <div style="display:flex; flex-direction:column; gap:1.5rem;">
                            ${registros.length > 0 ? registros.map(r => `
                                <div style="border-left:3px solid #000; padding-left:1.25rem;">
                                    <div style="font-size:0.875rem; font-weight:700; color:#000; margin-bottom:4px; text-transform:uppercase;">
                                        ${r.aluno_nome ? `DISCUSSÃO SOBRE: ${r.aluno_nome}` : 'NOTA GERAL DO CONSELHO'}
                                    </div>
                                    <div style="font-size:1rem; color:#000; line-height:1.6;">${r.texto}</div>
                                </div>
                            `).join('') : '<p style="font-style:italic;">Não houve registros de discussões nesta sessão.</p>'}
                        </div>
                    </section>

                    <!-- Seção 3: Encaminhamentos Determinados -->
                    <section style="margin-bottom:2.5rem;">
                        <h3 style="font-size:1.1rem; border-bottom:1px solid #333; padding-bottom:5px; margin-bottom:1.25rem; color:#000; text-transform:uppercase; font-weight:800;">3. ENCAMINHAMENTOS E PROVIDÊNCIAS</h3>
                        ${encaminhamentos.length > 0 ? `
                            <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
                                <thead>
                                    <tr style="background:#f5f5f5;">
                                        <th style="border:1px solid #ddd; padding:8px; text-align:left;">Aluno</th>
                                        <th style="border:1px solid #ddd; padding:8px; text-align:left;">Setor / Responsável</th>
                                        <th style="border:1px solid #ddd; padding:8px; text-align:left;">Encaminhamento</th>
                                        <th style="border:1px solid #ddd; padding:8px; text-align:center;">Expectativa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${encaminhamentos.map(e => `
                                        <tr>
                                            <td style="border:1px solid #ddd; padding:8px; font-weight:700;">${e.aluno_nome || 'A Turma (Geral)'}</td>
                                            <td style="border:1px solid #ddd; padding:8px;">${e.setor_tipo} <br><small style="color:#666">${e.target_users || 'Setor'}</small></td>
                                            <td style="border:1px solid #ddd; padding:8px;">${e.texto}</td>
                                            <td style="border:1px solid #ddd; padding:8px; text-align:center;">${e.data_expectativa ? new Date(e.data_expectativa).toLocaleDateString('pt-BR') : '—'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        ` : '<p style="font-style:italic;">Não houve encaminhamentos gerados nesta sessão.</p>'}
                    </section>

                    <div style="margin-top:4rem; text-align:center; font-size:0.75rem; color:#999;">
                        Documento gerado eletronicamente via Sistema Vértice Acadêmico em ${new Date().toLocaleString()}
                    </div>
                </div>
            `;
            container.innerHTML = html;
        } else {
            container.innerHTML = `<div style="padding:2rem; text-align:center; color:var(--color-danger);">⚠️ ${result.message}</div>`;
        }

    } catch (e) {
        console.error('Erro ao consolidar Ata:', e);
        container.innerHTML = '<div style="text-align:center; padding:3rem; color:var(--color-danger); font-weight:600;">⚠️ Falha na comunicação com o servidor.</div>';
    }
}
