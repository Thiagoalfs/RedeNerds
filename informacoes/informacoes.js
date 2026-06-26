const items = [
    { name: "Logistical Transporter", img:"https://wiki.aidancbrady.com/w/images/aidancbrady/9/90/Logistical_Transporters.gif", mod: "Mekanism" },
    { name: "256k Crafting Storage", img:"https://guide-assets.appliedenergistics.org/minecraft-1.21.1/ae2/items-blocks-machines/crafting_cpu_multiblock_blockimage11@4.enchbOfiNSch.png", mod:"AE2"},
    { name: "256k Crafting Accelerator", img:"", mod:"AE2"},
    { name: "Special IO Port", img:"https://guide-assets.appliedenergistics.org/minecraft-1.21.1/ae2/items-blocks-machines/spatial_io_port_blockimage1@4.dVkocR5XhQR3.png", mod: "AE2"},
    { name: "Digital Miner", img:"https://wiki.aidancbrady.com/w/images/aidancbrady/thumb/1/14/Digital_Miner.png/225px-Digital_Miner.png", mod: "Mekanism"},
    { name: "Seed Reprocessor", img:"https://blakesmods.com/assets/mysticalagriculture/v7/blocks/seed_reprocessor.png", mod:"Mystical Agriculture"},
    { name: "Logistical Sorter", img:"https://wiki.aidancbrady.com/w/images/aidancbrady/thumb/b/bd/Logistical_Sorter.png/225px-Logistical_Sorter.png", mod:"Mekanism"},
    { name: "Hopper (Fabricação liberada)", img:"https://static.wikia.nocookie.net/minecraft/images/e/e1/HopperOld.png/revision/latest/scale-to-width/360?cb=20190905231536", mod:"Minecraft"},
    { name: "Thorn Pendant", img:"https://i.mcmod.cn/item/icon/128x128/31/314251.png", mod:"Artifacts"},
    { name: "Building Gadget", img:"https://media.forgecdn.net/avatars/thumbnails/161/452/64/64/636672128890190531.png", mod:"Building Gadgets"},
    { name: "Rotten Egg", img:"", mod:"Mob Grinding Utils"},
    { name: "Planarium", img:"https://www.minecraft-guides.com/wp-content/uploads/mcg-icons/other%20mods/ars_nouveau__planarium.png", mod:"Ars Nouveau"},
    { name: "Eternal Water Block", img: "https://evilcraft.rubensworks.net/book/assets/icon/evilcraft__eternal_water.png", mod:"EvilCraft"},
    { name: "Eternal Water Bucket", img:"https://evilcraft.rubensworks.net/book/assets/icon/evilcraft__bucket_eternal_water.png", mod:"EvilCraft"},
    { name: "Black Hole", img:"", mod:"Oritech"},
    { name: "Arcane Catalyst", img:"https://api.moddedmc.wiki/api/v1/docs/oritech/asset/oritech:item/enchantment_catalyst_block?", mod:"Oritech"},
    { name: "Enchanter", img:"https://ftbwiki.org/images/thumb/9/94/Block_Enchanter_%28EnderIO%29.png/192px-Block_Enchanter_%28EnderIO%29.png.webp", mod:"EnderIO"}
];

function render(list) {
    document.getElementById("list").innerHTML = list.length === 0
    ? '<p style="text-align:center;color:#888;padding:1rem">Nenhum item encontrado</p>'
    : list.map(i => `
        <div class="item">
            <img src="${i.img}" alt="${i.name}" onerror="this.src='https://static.wikia.nocookie.net/minecraft_gamepedia/images/c/cd/Barrier_%28held%29_JE1_BE1.png/revision/latest?cb=20190811152525'">
            <span>${i.name} - ${i.mod}</span>
        </div>`).join("");
}

function filterItems() {
    const q = document.getElementById("search").value.toLowerCase();
    render(items.filter(i => 
        i.name.toLowerCase().includes(q) || 
        (i.mod && i.mod.toLowerCase().includes(q))
    ));
}

render(items);